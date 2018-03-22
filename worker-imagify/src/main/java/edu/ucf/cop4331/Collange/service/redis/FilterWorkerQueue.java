package edu.ucf.cop4331.Collange.service.redis;

import edu.ucf.cop4331.Collange.service.redis.dto.FilterCompleteMessage;
import edu.ucf.cop4331.Collange.service.redis.dto.FilterWorkerMessage;
import java.io.IOException;

public class FilterWorkerQueue extends JedisHandler {

    private static final String WaitingQueueRedisIdentifier = "FilterWaitingQueue";
    private static final String CompletedMapRedisIdentifier = "FilterCompletedMap";

    public FilterWorkerQueue(JedisHandler context){
        super(context.getSession());
    }

    public FilterWorkerMessage dequeueJob(){
        try {
            QueueMessage<FilterWorkerMessage> msg = super.dequeue(WaitingQueueRedisIdentifier);
            if(msg != null){
                return msg.getObject();
            }
        } catch (IOException e) {
            e.printStackTrace();
        }
        return null;
    }

    public FilterWorkerMessage dequeueJob(long timeoutMs, long sleepTime){
        try {
            QueueMessage<FilterWorkerMessage> msg = super.dequeue(WaitingQueueRedisIdentifier,
                timeoutMs, sleepTime);
            if(msg != null){
                return msg.getObject();
            }
        } catch (IOException e) {
            e.printStackTrace();
        }
        return null;
    }

    public boolean markJobComplete(String transactionId, FilterCompleteMessage results){
        return super.putMap(CompletedMapRedisIdentifier,
                transactionId,
                results,
                FilterCompleteMessage.class);
    }
}