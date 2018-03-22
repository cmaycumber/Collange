package edu.ucf.cop4331.Collange.worker;

import edu.ucf.cop4331.Collange.service.redis.FilterWorkerQueue;
import edu.ucf.cop4331.Collange.service.redis.JedisHandler;
import edu.ucf.cop4331.Collange.service.redis.dto.FilterWorkerMessage;

public class FilterWorker {

    private static final String ENV_REDISURL = "REDIS_URL";
    private static final String WaitingQueueRedisIdentifier = "FilterWaitingQueue";
    private static final String CompletedQueueRedisIdentifier = "FilterCompletedQueue";

    public static void main(String[] args){
        // Initialize Redis Session.
        JedisHandler redisSession = new JedisHandler(System.getenv(ENV_REDISURL));

        // Initialize Filter Worker Queue Handler.
        FilterWorkerQueue jobQueue = new FilterWorkerQueue(redisSession);

        if(!jobQueue.isConnected()){
            System.out.println("FilterWorker.ERROR: Redis Session failed to connect.");
            System.exit(1);
        }else{
            System.out.println("FilterWorker.DEBUG: Redis Session Established.");
        }

        while(true) {
            // Attempt to pop a message off the queue.
            FilterWorkerMessage message = jobQueue.dequeueJob(10000, 1000);
            if(message == null){
                System.out.println("FilterWorker.DEBUG: Listener timed out while waiting for messages.");
            }else{
                // Begin processing the message.
                System.out.println("FilterWorker.INFO: Listener received message\n\tTransaction ID: " + message.transactionId);
            }

            // If a second argument is given, run indefinitely.ß
            if(args == null && args.length <= 1){
                break;
            }
        }
    }
}