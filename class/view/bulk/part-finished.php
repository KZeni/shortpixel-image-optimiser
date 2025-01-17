<?php

namespace ShortPixel;

?>
<section class="panel finished" data-panel="finished">
  <div class="panel-container">

    <h3 class="heading"><span><img src="<?php echo \wpSPIO()->plugin_url('res/img/robo-slider.png'); ?>"></span>
      <?php _e('The ShortPixel Bulk Processing is finished' ,'shortpixel-image-optimiser'); ?>
      <div class='average-optimization'>
          <p><?php _e('Average this run','shortpixel-image-optimiser'); ?></p>
          <svg class="opt-circle-average" viewBox="-10 0 150 140">
                        <path class="trail" d="
                            M 50,50
                            m 0,-46
                            a 46,46 0 1 1 0,92
                            a 46,46 0 1 1 0,-92
                            " stroke-width="16" fill-opacity="0">
                        </path>
                        <path class="path" d="
                            M 50,50
                            m 0,-46
                            a 46,46 0 1 1 0,92
                            a 46,46 0 1 1 0,-92
                            " stroke-width="16" fill-opacity="0" style="stroke-dasharray: 289.027px, 289.027px; stroke-dashoffset: 180px;">
                        </path>
                        <text class="text" x="50" y="50"><?php _e('N/A', 'shortpixel-image-optimiser'); ?></text>
            </svg>

      </div>
    </h3>

    <?php $this->loadView('bulk/part-progressbar', false); ?>
		<span class='hidden' data-check-media-total data-stats-media="total">0</span>

    <div class='bulk-summary' data-check-visibility data-control="data-check-media-total">
      <div class='heading'>
        <span><i class='dashicons dashicons-images-alt2'>&nbsp;</i> <?php _e('Media Library','shortpixel-image-optimiser'); ?></span>
        <span>
              <span class='line-progressbar'>
                <span class='done-text'><i data-stats-media="percentage_done"></i> %</span>
                <span class='done' data-stats-media="percentage_done" data-presentation="css.width.percentage"></span>
              </span>
        </span>
        <span><?php _e('Processing','shortpixel-image-optimiser') ?>: <i data-stats-media="in_process">-</i></span>

      </div>

      <div>
        <span><?php _e('Processed','shortpixel-image-optimiser'); ?>: <i data-stats-media="done">-</i></span>

        <span><?php _e('Images Left','shortpixel-image-optimiser'); ?>: <i data-stats-media="in_queue">-</i></span>
        <span><?php _e('Errors','shortpixel-image-optimiser'); ?>: <i data-check-media-fatalerrors data-stats-media="fatal_errors" class='error'>- </i>
					<span class="display-error-box" data-check-visibility data-control="data-check-media-fatalerrors" ><label title="<?php _e('Show Errors', 'shortpixel-image-optimiser'); ?>">
						<input type="checkbox" name="show-errors" value="show" data-action='ToggleErrorBox' data-errorbox='media' data-event='change'><?php _e('Show Errors','shortpixel-image-optimiser'); ?></label>
				 </span>
        </span>


      </div>

    </div>


		<div data-error-media="message" data-presentation="append" class='errorbox media'>
			<?php if(property_exists($this->view, 'mediaErrorLog') && $this->view->mediaErrorLog !== false)
			{
				echo $this->view->mediaErrorLog;
			}
			?>
		</div>

		<!-- ****** CUSTOM ********  --->
		<span class='hidden' data-check-custom-total data-stats-custom="total">0</span>

    <div class='bulk-summary' data-check-visibility data-control="data-check-custom-total">
      <div class='heading'>
        <span><i class='dashicons dashicons-open-folder'>&nbsp;</i> <?php _e('Custom Media','shortpixel-image-optimiser'); ?></span>
        <span>
              <span class='line-progressbar'>
                <span class='done-text'><i data-stats-custom="percentage_done"></i> %</span>
                <span class='done' data-stats-custom="percentage_done" data-presentation="css.width.percentage"></span>
              </span>
        </span>
  			<span><?php _e('Processing','shortpixel-image-optimiser') ?>: <i data-stats-custom="in_process">-</i></span>

      </div>
      <div>
        <span><?php _e('Processed','shortpixel-image-optimiser'); ?>: <i data-stats-custom="done">-</i></span>

        <span><?php _e('Images Left', 'shortpixel-image-optimiser') ?>: <i data-stats-custom="in_queue">-</i></span>
        <span><?php _e('Errors','shortpixel-image-optimiser') ?>: <i data-check-custom-fatalerrors  data-stats-custom="fatal_errors" class='error'>-</i>
								<span class="display-error-box" data-check-visibility data-control="data-check-custom-fatalerrors" ><label title="<?php _e('Show Errors', 'shortpixel-image-optimiser'); ?>">
									<input type="checkbox" name="show-errors" value="show" data-action='ToggleErrorBox' data-errorbox='custom' data-event='change'>Show Errors</label>
							 </span>
				</span>

      </div>

    </div>

    <div data-error-custom="message" data-presentation="append" class='errorbox custom'>
			<?php if(property_exists($this->view, 'customErrorLog') && $this->view->customErrorLog !== false)
			{
				echo $this->view->customErrorLog;
			}
			?>
		</div>


    <nav>
      <button class='button finish' type="button" data-action="FinishBulk" id="FinishBulkButton">Finish Bulk Process</button>
    </nav>




  </div>
</section>
