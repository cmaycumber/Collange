<?php require_once('../Application.php');?>
<?php AuthSession::protect();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php App::buildHtmlHead('My Library');?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.4.0/min/dropzone.min.css"/>
    <style>
        .lazy {
            opacity: 0;
        }
        .library-image {
            width:100%;
            padding:15px;
            -opacity: 1;
            transition: opacity 0.3s;
        }
        .dropzone {
            width:100%;
            height:100%;
            border: 2px dashed #0087F7;
        }
        .dz-message {
            font-weight:400;
        }
    </style>
</head>
<body class="app header-fixed sidebar-fixed">
<?php echo App::buildPageNavbar();?>
<div class="app-body">
    <?php App::buildPageSidebar();?>

    <!-- Main content -->
    <main class="main">
        <!-- Breadcrumb -->
        <ol class="breadcrumb">
            <li class="breadcrumb-item">My Library</li>
            <!-- Breadcrumb Menu-->
            <li class="breadcrumb-menu d-md-down-none">
                <div class="btn-group" role="group" aria-label="Button group">
                    <a class="btn" href="#"><i class="icon-speech"></i></a>
                    <a class="btn" href="./"><i class="icon-graph"></i> &nbsp;Explore</a>
                    <a class="btn" href="#"><i class="fa fa-upload"></i> &nbsp;Import Photo</a>
                </div>
            </li>
        </ol>
        <div class="container-fluid" id="library-view">
            <div class="animated fadeIn">
                <div class="row">
                    <?php
                    $imageCount = 0;
                    foreach(Image::getAll(DBSession::getSession(), array(
                        'ownerId'=>AuthSession::getUser()->id)) as $Image){
                        $imageCount++;
                        $cachedURL = S3EphemeralURLHandler::get($Image['key']);
                        if($cachedURL == null){
                            $cachedURL = S3Handler::createSignedGETUrl($Image['key']);
                            S3EphemeralURLHandler::set($Image['key'], $cachedURL);
                        }
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 col-xs-1 img-responsive">
                            <center>
                                <img attr-lazysrc="<?php echo $cachedURL;?>" class="library-image lazy" alt="<?php echo $Image['fileName'];?>"/>
                            </center>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
    </main>
    
</div>

<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalTitle">Import from your computer</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalDropZone">
                <form action="/api.php?upload" class="dropzone needsclick dz-clickable">
                    <div class="dz-message">
                        Drop files here or click to upload.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="processingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Uploading Image</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                ...
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Error</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <b><span class="text-danger" id="errorMsg"></span></b>
            </div>
        </div>
    </div>
</div>

<?php App::buildPageFooter();?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.4.0/min/dropzone.min.js"></script>
<script>
    function blurContainer(container, opacity){
        if(container != undefined){
            container.css('opacity', opacity);
        }
    }
    $(document).ready(function(){
        <?php if($imageCount == 0){ ?>
            /**
             * Initialize the upload modal.
             */
            $('#uploadModal').modal({show: true});
        <?php } ?>

        /**
         * Lazy-load all of the images.
         */
        var libraryView = $('#library-view');
        libraryView.find('img[attr-lazysrc]').each(function(index){
            $(this).attr('src', $(this).attr('attr-lazysrc')).removeClass('lazy');
        });

        /**
         * Start Dropzone.JS
         */
        var uploader = new Dropzone("#modalDropZone", {url: '/api.php?upload'});
        uploader.on('dragover', function(e){
            blurContainer(libraryView, '0.25');
        });
        uploader.on('dragleave', function(e){
            blurContainer(libraryView, '1');
        });
        // Upload complete.
        uploader.on('queuecomplete', function(e){
            $.when(function(){
                $('#processingModal').modal('hide');
                blurContainer(libraryView, '1');
            }).then(function(){
                window.location.reload();
            });
        });
        // Upload error.
        uploader.on('error', function(file, msg, xhr){
            $('#processingModal').modal('hide');
            $('#errorMsg').text(msg);
            $('#errorModal').modal('show');
        });
    });
</script>
</body>
</html>