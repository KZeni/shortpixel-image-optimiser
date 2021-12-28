<?php
namespace ShortPixel;
use ShortPixel\ShortpixelLogger\ShortPixelLogger as Log;
use ShortPixel\Controller\OptimizeController as OptimizeController;
use ShortPixel\Controller\BulkController as BulkController;

use ShortPixel\Controller\Queue\Queue as Queue;
use ShortPixel\Controller\ApiController as ApiController;
use ShortPixel\Controller\ResponseController as ResponseController;

class WpCliController
{
    public static $instance;

    protected static $ticks = 0;
    protected static $emptyq = 0;

    public function __construct()
    {
        $this->initCommands();
    }

    public static function getInstance()
    {
        if (is_null(self::$instance))
          self::$instance = new WpCliController();

        return self::$instance;
    }


    protected function initCommands()
    {
        \WP_CLI::add_command('spio', '\ShortPixel\SpioSingle');
				\WP_CLI::add_command('spio bulk', '\ShortPixel\SpioBulk');

    }

}

/**
* ShortPixel Image Optimizer
*
*
*/
class SpioCommandBase
{

     protected static $runs = 0;
      /**
     * Add a single item to the queue.
     *
     * ## OPTIONS
     *
     * <id>
     * : MediaLibrary ID
     *
     *
	   * [--type=<type>]
	   * : media or custom
	   * ---
	   * default: media
	   * options:
	   *   - media
	   *   - custom
	   * ---
	 	 *
   	 * [--run]
   	 * : Directly start after enqueuing
	 	 *
		 * [--complete]
	   * : Run until the queue is done.
     *
     * ## EXAMPLES
     *
     *   wp spio enqueue 1
     *
     * @when after_wp_load
     */
    public function enqueue($args, $assoc)
    {
        $controller = $this->getOptimizeController();

				$type = isset($assoc['type']) ? sanitize_text_field($assoc['type']) : 'media';

        if (! isset($args[0]))
        {
          \WP_CLI::Error(__('Specify an Media Library Item ID', 'shortpixel-image-optimiser'));
          return;
        }
        $id = intval($args[0]);

        $fs = \wpSPIO()->filesystem();
        $imageObj = $fs->getImage($id, $type);

				if ($imageObj === false)
				{
					 \WP_CLI::Error(__('Image object not found / non-existing in database by this ID', 'shortpixel-image-optimiser'));
				}

        $result = $controller->addItemtoQueue($imageObj);

			//	$complete = isset($assoc['complete']) ? true : false;


        if ($result->status == 1)
				{

          \WP_CLI::Success($result->result->message);
					\WP_CLI::Line (__('You can optimize images via the run command', 'shortpixel-image-optimiser'));

					if (isset($assoc['run']))
					{
							$this->run($args, $assoc);
					}
				}


        elseif ($result->status == 0)
        {
          \WP_CLI::Error(sprintf(__("Adding this item: %s", 'shortpixel_image_optimiser'), $result->result->message) );
        }

				$this->status($args, $assoc);
    }


   /**
   * Runs the current queue.
   *
   * ## OPTIONS
   *
   * [--ticks=<number>]
   * : How much times the queue runs
	 * ---
	 * default: 20
	 * ---
   *
   * [--wait=<miliseconds>]
   * : How much miliseconds to wait for next tick.
	 * ---
	 * default: 3000
	 * ---
	 *
   * [--complete]
   * : Run until either preparation is done or queue is completely finished.
   *
   * [--queue=<name>]
   * : Either 'media' or 'custom' . Omit to run both.
   * ---
   * default: media,custom
   * ---
   *
   * ## EXAMPLES
   *
	 * 	 wp spio run
   *   wp spio run --ticks=20 --wait=3000
	 * 	 wp spio run --complete
	 *   wp spio run --queue=media
   *
   *
   * @when after_wp_loadA
   */
    public function run($args, $assoc)
    {
        if ( isset($assoc['ticks']))
          $ticks = intval($assoc['ticks']);
        else
          $ticks = 20;

				$complete = false;
        if ( isset($assoc['complete']))
        {
            $ticks = -1;
						$complete = true;
        }

        if (isset($assoc['wait']))
          $wait = intval($assoc['wait']);
        else
          $wait = 3000;

				$queue = $this->getQueueArgument($assoc);

      //  $progress = \WP_CLI\Utils\make_progress_bar( 'This run (ticks) ', $ticks );

        while($ticks > 0 || $complete == true)
        {
           $bool = $this->runClick($queue);
           if ($bool === false)
           {
             break;
					//	 $complete = false;
           }

           $ticks--;

					 \WP_CLI::line('Tick '  . $ticks);
           usleep($wait * 1000);
        }

				// Done.
				$this->showResponses();

    }

    protected function runClick($queueTypes)
    {
        $controller = $this->getOptimizeController();
        $results = $controller->processQueue($queueTypes);

				foreach($queueTypes as $qname)
				{

					$qresult = $results->$qname;

	        if (! is_null($qresult->message))
	        {
	          \WP_CLI::line($qresult->message); // Single Response ( ie prepared, enqueued etc )
	        }

		        // Result after optimizing items and such.
		        if (isset($qresult->results))
		        {
		           foreach($qresult->results as $item)
		           {
		               $result = $item->result;
		            //  if ($item->result->status == ApiController::STATUS_ENQUEUED)
		                 \WP_CLI::line($result->message);
		                 if (property_exists($result, 'improvements'))
		                 {
		                    $improvements = $result->improvements;
		                    if (isset($improvements['main']))
		                       \WP_CLI::Success( sprintf(__('Image optimized by %d %% ', 'shortpixel-image-optimiser'), $improvements['main'][0]));

		                    if (isset($improvements['thumbnails']))
		                    {
		                      foreach($improvements['thumbnails'] as $thumbName => $optData)
		                      {
		                         \WP_CLI::Success( sprintf(__('%s optimized by %d %% ', 'shortpixel-image-optimiser'), $thumbName, $optData[0]));
		                      }
		                    }
		                 }

		           }
	        }
				}

				// Combined Status. Implememented from shortpixel-processor.js
	      $mediaStatus = $customStatus = 100;

				if (property_exists($results, 'media') && property_exists($results->media, 'qstatus') )
				{
					 $mediaStatus = $results->media->qstatus;
				}
				if (property_exists($results, 'custom') && property_exists($results->custom, 'qstatus') )
				{
					 $customStatus = $results->custom->qstatus;
				}

	        // The lowest queue status (for now) equals earlier in process. Don't halt until both are done.
	        if ($mediaStatus <= $customStatus)
	          $combinedStatus = $mediaStatus;
	        else
	          $combinedStatus = $customStatus;

	      //if ($combinedStatus == 100)
	      //  return false; // no status in this request.

      	if ($combinedStatus == Queue::RESULT_QUEUE_EMPTY)
        {
           \WP_CLI::log('Queue reports processing has finished');
           return false;
        }
        elseif($combinedStatus == Queue::RESULT_PREPARING_DONE)
        {
           \WP_CLI::log('Bulk Preparing is done.');
					 return false;
        }

      //  if ($mediaResult->status !==)
      return true;
    }

	 /** Shows the current status of the queue
	 *
   * [--show-debug]
   * :  Dump more information for debugging
	 *
	 *
   * ---
   *
   * ## EXAMPLES
   *
   *   wp spio status [--show-debug]
   *
		*/
		public function status($args, $assoc)
		{
				$queue = $this->getQueueArgument($assoc);
				$optimizeController = $this->getOptimizeController();

				$startupData = $optimizeController->getStartupData();

				foreach($queue as $queue_name)
				{
					  	//$Q = $optimizeController->getQueue($queue_name);
							$stats = $startupData->$queue_name->stats;


							if ($stats->is_finished)
							{
								 $line = sprintf("Queue %s is finished: %s done %s fatal errors", $queue_name, $stats->done,  $stats->fatal_errors);
							}
							elseif ($stats->is_running)
							{
								 $line = sprintf("Queue %s is running: %s in queue, %s done (%s percent)  %s fatal errors", $queue_name, $stats->in_queue, $stats->done, $stats->percentage_done,  $stats->fatal_errors);
							}
							elseif ($stats->is_preparing)
							{
								 $line = sprintf("Queue %s is preparing: %s in queue ", $queue_name, $stats->in_queue);
							}
							elseif ($stats->total > 0)
							{
								 $line = sprintf("Queue %s is waiting for action, %s waiting %s done %s errors", $queue_name,  $stats->in_queue, $stats->done, $stats->fatal_errors );

							}

							\WP_CLI::log($line);

							if (isset($assoc['show-debug']))
							{
								 print_r($stats);
							}
				}

		}



		protected function showResponses()
		{
         $responses = ResponseController::getAll();

         foreach ($responses as $response)
         {
             if ($response->is('error'))
                \WP_CLI::Error($response->message, false);
             elseif ($response->is('success'))
                \WP_CLI::Success($response->message);
             else
               \WP_CLI::line($response->message);
         }
		}

		protected function getQueueArgument($assoc)
		{

	        if (isset($assoc['queue']))
	        {
	          if (strpos($assoc['queue'], ',') !== false)
	          {
	              $queue = explode(',', $assoc['queue']);
	              $queue = array_map('sanitize_text_field', $queue);
	          }
	          else
	            $queue = array(sanitize_text_field($assoc['queue']));
	        }
	        else
	          $queue = array('media', 'custom');

				return $queue;
		}

		// To ensure the bulk switch is ok.
		protected function getOptimizeController()
		{
						$optimizeController = new OptimizeController();
						return $optimizeController;
		}

} // Class