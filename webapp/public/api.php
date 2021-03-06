<?php
require_once('../Application.php');
AuthSession::protect();


/**
 * [AJAX] Handle User File Uploads
 */
if(isset($_GET['upload'])){
    // Retain the key attempted, in case we need to abort.
    $key = null;
    if(!empty($_FILES['file'])){
        // Import S3 Handler and Amazon S3 SDK.
        PHPLoader::loadModule('collange:S3Handler');

        // Check if upload is over ~50MB?
        if($_FILES['file']['size'] > 50485760){
            http_response_code(400);
            die(StaticResource::get('error_api_upload_maxsize'));
        }

        $filename = basename($_FILES['file']['name']);
        $type = strtolower(pathinfo($filename,PATHINFO_EXTENSION));
        if(!in_array($type, StaticResource::get('upload_allowed_types'))) {
            http_response_code(400);
            die(StaticResource::get('error_api_upload_filetype'));
        }

        // Extract image type and randomize name.
        $imageUUID = UUID::randomUUID();
        $key = $imageUUID . '.' . $type;

        // Make sure the image is a JPG.
        $location = ImageHandler::convertImageToJPG($_FILES['file']['tmp_name'], $type);
        $type = 'jpg';
        $key = $imageUUID . '.' . $type;

        // Strip any EXIF/PID info from the image.
        $location = ImageHandler::stripEXIFFromJPEG($location);

        // Make sure the image made it thru all of that conversion.
        if($location != null){
            $imageRcd = new Image($imageUUID, AuthSession::getUser()->id, $filename, '', $_FILES['file']['size'], 0, $type);
            $saveResult = $imageRcd->save();
            if($saveResult){
                // Upload the user's image.
                if(!S3Handler::upload($key, $location)){
                    http_response_code(400);
                    die(StaticResource::get('error_api_upload_unknown'));
                }

                // Save a thumbnail version.
                $thumb = ImageHandler::make_thumb($location);
                if(!empty($thumb)){
                    if(!S3Handler::upload($imageUUID . '_thumb.' . $type, $thumb)){
                        http_response_code(400);
                        die(StaticResource::get('error_api_upload_unknown'));
                    }
                    unlink($thumb);
                }

                // Upload successful, delete the local file now.
                unlink($location);

                // Cache a signed url for users to view the image, we're going to need it soon.
                $URL = S3Handler::createSignedGETUrl($key, '+1 hour');
                if(!empty($URL)){
                    PHPLoader::loadModule('collange:S3EphemeralURLHandler');
                    if(S3EphemeralURLHandler::set($key, $URL)){
                        Log::info('S3EphemeralURL('.$key.'): ' . $URL);
                        die();
                    }
                }
            }
        }
    }

    // Something bad happened.. Atleast try to delete their file..
    if(!S3Handler::delete($key)){
        Log::error('API.delete('.$key.'): Error');
    }

    http_response_code(400);
    die(StaticResource::get('error_api_upload_unknown'));
}


/**
 * [REDIRECT] Initialize an editing session.
 */
if(isset($_GET['edit'])){
    if(!empty($_GET['edit'])){
        // Make sure no ongoing session for this image.
        foreach(TransformSessionHandler::getSessions() as $i=>$Session) {
            if($Session['imageUuid'] == $_GET['edit']){
                header("Location: /transform.php?txId=".$Session['imageUuid']);
                die();
            }
        }

        $Image = null;
        foreach(Image::getAll(DBSession::getSession(), array(
            'ownerId'=>AuthSession::getUser()->id,
            'uuid'=>$_GET['edit'])
        ) as $i=>$img){
            $Image = $img;
        }
        if($Image != null){
            $ret = TransformSessionHandler::createSession($Image['fileName'], $Image['size'], $Image['uuid']);
            if($ret != null){
                header("Location: /transform.php?txId=".$ret);
                die();
            }else Log::error('Unable to create TransformSession(Image(uuid='.$_GET['edit'].'))');
        }else Log::error('Unable to retrieve Image(uuid='.$_GET['edit'].')');
    }

    // If we got here, an error occurred. Redirect back to library.
    header("Location: /library.php?error");
    die();
}


/**
 * [REDIRECT] Handle Image public/private toggling.
 */
if(isset($_GET['sharing'])){
    $Image = null;
    foreach(Image::getAll(DBSession::getSession(), array(
            'ownerId'=>AuthSession::getUser()->id,
            'uuid'=>$_GET['sharing'])
    ) as $i=>$img){
        $Image = $img;
    }
    if($Image != null){
        $new = 1;
        if($Image['shared']){
            $new = 0;
        }
        if(DBSession::getSession()->query("UPDATE `image` SET `shared`='$new' WHERE `id`='".$Image['id']."'")){
            header("Location: /library.php");
            die();
        }
    }

    // If we got here, an error occurred. Redirect back to library.
    header("Location: /library.php?error");
    die();
}


/**
 * [AJAX] Apply a filter to an image.
 */
if(isset($_GET['filter'])){
    $Image = null;
    foreach(Image::getAll(DBSession::getSession(), array(
            'ownerId'=>AuthSession::getUser()->id,
            'uuid'=>$_GET['image'])
    ) as $i=>$img){
        $Image = $img;
    }
    if($Image == null && !empty($_GET['key'])){
        $Image = array();
        $Image['key'] = $_GET['key'];
    }
    $filter = $_GET['filter'];
    if($Image != null && !empty($_GET['txId']) && !empty($_GET['revisionId']) && !empty($filter)) {
        $FilterName = StaticResource::get('filters')[$filter];
        if(!empty($FilterName)) {
            PHPLoader::loadModule('collange:TransformImageTransactionHandler');
            $EventUUID = TransformImageRequestHandler::enqueue(
                (!empty($Image['key']) ? $Image['key'] : ($Image['uuid'] . '.' . $Image['ext'])),
                $_GET['txId'],
                $_GET['revisionId'],
                $filter
            );
            if (!empty($EventUUID)) {
               $revId = TransformSessionHandler::reviseSession($_GET['txId'], 'Applied ' . $FilterName, $EventUUID);
               if(!empty($revId)){
                   die(json_encode(array(
                       'txId'=>$_GET['txId'],
                       'revisionId'=>$revId,
                       'EventUUID'=>$EventUUID
                   )));
               }
            }
        }
    }
    http_response_code(400);
    die(StaticResource::get('error_default'));
}


/**
 * Wait for Filter event to complete.
 * Update the revision session information
 * and return the image s3 signed URL when done.
 * 10 Second timeout.
 */
if(isset($_GET['loadEventUUID'])){
    if(!empty($_GET['loadEventUUID'])){
        // Verify this user owns the eventid.
        $Session = TransformSessionHandler::getSession($_GET['txId']);
        $Revision = null;
        $RevisionKey = null;
        if($Session != null) {
            foreach ($Session['events'] as $j => $Event) {
                if ($Event['EventUUID'] == $_GET['loadEventUUID']) {
                    $RevisionKey = $j;
                    $Revision = $Event;
                    break;
                }
            }
            if ($Revision != null) {
                $start = time();
                while ((time() - $start) < 10) {
                    PHPLoader::loadModule('collange:TransformImageTransactionHandler');
                    $Resp = TransformImageResponseHandler::get($_GET['loadEventUUID']);
                    if (!empty($Resp)) {
                        $Obj = json_decode($Resp, true);
                        $key = $Obj['key'];
                        // Cache a signed url for users to view the image, we're going to need it soon.
                        $Obj['image_uri'] = S3Handler::createSignedGETUrl($key, '+1 hour');
                        if (!empty($Obj['image_uri'])) {
                            PHPLoader::loadModule('collange:S3EphemeralURLHandler');
                            if (S3EphemeralURLHandler::set($key, $Obj['image_uri'])) {
                                Log::info('S3EphemeralURL(' . $key . '): ' . $Obj['image_uri']);
                                // Update session data.
                                $Revision['key'] = $key;
                                $Revision['EventUUID'] = null;
                                $Session['events'][$RevisionKey] = $Revision;
                                TransformSessionHandler::setSession($Session);
                                die(json_encode($Obj));
                            }
                        }
                    }else sleep(1);
                }
            }
        }
    }

    http_response_code(400);
    die(StaticResource::get('error_default'));
}

/**
 * [REDIRECT] Save a filtered image.
 */
if(isset($_GET['save'])){
    $sessionId = $_GET['save'];
    $revisionId = $_GET['rId'];
    if(!empty($revisionId) && !empty($sessionId)){
        $Session = TransformSessionHandler::getSession($sessionId);
        $Revision = null;
        $RevisionKey = null;
        if($Session != null) {
            foreach ($Session['events'] as $j => $Event) {
                if ($Event['revisionId'] == $revisionId) {
                    $RevisionKey = $j;
                    $Revision = $Event;
                    break;
                }
            }
            if($Revision != null){
                $ImageUUID = UUID::randomUUID();
                // Move the image away from the filter tmp dir.
                if(S3Handler::copy($Revision['key'], $ImageUUID.'.jpg') && S3Handler::copy($ImageUUID.'.jpg', $ImageUUID.'_thumb.jpg')){
                    if(S3Handler::delete($Revision['key'])){
                        $q = DBSession::getSession()->query("INSERT INTO `image` (`ownerId`, `fileName`, `size`, `shared`, `createdDate`, `uuid`, `ext`) VALUES ('".AuthSession::getUser()->id."', '".$Session['originalImageName']."', '".$Session['originalImageSize']."', '0', '".time()."', '$ImageUUID', 'jpg')");
                        if($q){
                            $Sessions = TransformSessionHandler::getSessions();
                            foreach($Sessions as $k=>$s){
                                if($s['sessionId'] == $sesisonId){
                                    $Sessions[$k] = null;
                                    unset($Sessions[$k]);
                                }
                            }
                            TransformSessionHandler::setSessions(array());

                            Log::info("API.save(".$ImageUUID."): success");
                            header("Location: library.php");
                            die();
                        }else Log::error("API.save(".$ImageUUID."): Error - " . DBSession::getSession()->error);
                    }else Log::error('API.save().Error: Unable to delete ' . $Revision['key']);
                }else Log::error('API.save().Error: Unable to copy ' . $Revision['key']);
            }
        }
    }
    header("Location: library.php?error");
    die();
}



/**
 * Developer session data.
 */
if(isset($_GET['developer'])){
    die(json_encode($_SESSION));
}
?>