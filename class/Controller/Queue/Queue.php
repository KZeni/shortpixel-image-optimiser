<?php
namespace ShortPixel\Controller\Queue;

use ShortPixel\Model\Image\ImageModel as ImageModel;

abstract class Queue
{
    protected $q;
    protected static $instance;
    protected static $results;

    const PLUGIN_SLUG = 'SPIO';
    const QUEUE_NAME = 'base';

    // Result status for Run function
    const RESULT_ITEMS = 1;
    const RESULT_PREPARING = 2;
    const RESULT_EMPTY = 3;
    const RESULT_ERROR = -1;
    const RESULT_UNKNOWN = -10;

    /* Result status (per item) to communicate back to frontend */
/*    const FILE_NOTEXISTS = -1;
    const FILE_ALREADYOPTIMIZED = -2;
    const FILE_OK = 1;
    const FILE_SUCCESS = 2;
    const FILE_WAIT = 3; */

    abstract protected function createNewBulk($args);
    abstract protected function prepare();

    public static function getInstance()
    {
       if (is_null(self::$instance))
       {
          $class = get_called_class();
          self::$instance = new $class();
       }

       return self::$instance;
    }

    /** Enqueues a single items into the urgent queue list
    *   - Should not be used for bulk images
    * @param ImageModel $mediaItem An ImageModel (CustomImageModel or MediaLibraryModel) object
    * @return mixed
    */
    public function addSingleItem(ImageModel $imageModel)
    {
       //if (! $mediaItem->isProcessable())
      //  return false;
       $preparing = $this->getStatus('preparing');

       $qItem = $this->imageModelToQueue($imageModel);
       $item = array('id' => $imageModel->get('id'), 'value' => $qItem);
       $numitems = $this->q->withOrder(array($item), 5)->withRemoveDuplicates()->enqueue(); // enqueue returns numitems

       $this->q->setStatus('preparing', $preparing); // add single should not influence preparing status.
       return $numitems;
    }

    public function run()
    {

       $result = new \stdClass();
       $result->qstatus = self::RESULT_UNKNOWN;
       $result->items = null;

       if ( $this->getStatus('preparing'))
       {
            $prepared = $this->prepare();
            $result->qstatus = self::STATUS_PREPARING;
            $result->items = $prepared; // number of items.
       }
       elseif ($this->getStatus('bulk_running'))
       {
            $items = $this->deQueue();
       }
       else
       {
            $items = $this->deQueuePriority();
       }

       if (isset($items)) // did a dequeue.
       {
         if (count($items) == 0)
         {
           $result->qstatus = self::RESULT_EMPTY;
         }
         else
         {
           $result->qstatus = self::RESULT_ITEMS;
         }
          $result->items = $items;

       }

       return $result;
    }

    public function getQueueName()
    {
       return self::QUEUE_NAME;
    }

    protected function getStatus($name = false)
    {
        return $this->q->getStatus($name);
    }

    protected function deQueue()
    {
       $items = $this->q->deQueue();
       $items = array_map(array($this, 'queueToMediaItem'), $items);
       return $items;
    }

    protected function deQueuePriority()
    {
      $items = $this->q->deQueue(array('onlypriority' => true));
    //  echo "R/A/W : "; var_dump($items);
    //echo "DQPRIO - BEFORE "; var_dump($items);
      $items = array_map(array($this, 'queueToMediaItem'), $items);
//  echo "DQPRIO - AFTER "; var_dump($items);
      return $items;
    }


    protected function queueToMediaItem($qItem)
    {
        $item = new \stdClass;

        $item = $qItem->value;
        $item->_queueItem = $qItem;

        $item->item_id = $qItem->item_id;
        $item->tries = $qItem->tries;

        return $item;
    }

    protected function mediaItemToQueue($mediaItem)
    {
        unset($mediaItem->item_id);
        unset($mediaItem->tries);

        $qItem = $mediaItem->_queueItem;

        unset($mediaItem->_queueItem);

        $qItem->value = $mediaItem;
        return $qItem;
    }


    // This might be a general implementation - This should be done only once!
    protected function imageModelToQueue(ImageModel $imageModel)
    {

        $item = new \stdClass;
        $item->compressionType = false;

        $urls = $imageModel->getOptimizeUrls();

        if ($imageModel->getMeta('compressionType'))
          $item->compressionType = $imageModel->getMeta('compressionType');

        $item->urls = apply_filters('shortpixel_image_urls', $urls, $imageModel->get('id'));

        return $item;
    }

    public function itemFailed($item, $fatal = false)
    {
        $qItem = $this->mediaItemToQueue($item); // convert again
        $this->q->itemFailed($qItem, $fatal);
        $this->q->updateItemValue($qItem);
    }

    public function itemDone ($item)
    {
      $qItem = $this->mediaItemToQueue($item); // convert again
      $this->q->itemDone($qItem);


    }

    public function getShortQ()
    {
        return $this->q;
    }


} // class
