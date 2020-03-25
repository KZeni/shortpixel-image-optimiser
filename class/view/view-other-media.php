<?php
namespace ShortPixel;
use ShortPixel\ShortpixelLogger\ShortPixelLogger as Log;
use ShortPixel\Notices\NoticeController as Notices;

$fs = \wpSPIO()->filesystem();

if ( isset($_GET['noheader']) ) {
    require_once(ABSPATH . 'wp-admin/admin-header.php');
}
//$this->outputHSBeacon();
\ShortPixel\HelpScout::outputBeacon(\wpSPIO()->getShortPixel()->getApiKey());

echo $this->view->rewriteHREF;

?>
<div class="wrap shortpixel-other-media">
    <h2>
        <?php _e('Other Media optimized by ShortPixel','shortpixel-image-optimiser');?>
    </h2>

    <div class='toolbar'>

        <div>
          <?php
          $nonce = wp_create_nonce( 'sp_custom_action' );
          ?>
            <a href="upload.php?page=wp-short-pixel-custom&action=refresh&_wpnonce=<?php echo $nonce ?>" id="refresh" class="button button-primary" title="<?php _e('Refresh custom folders content','shortpixel-image-optimiser');?>">
                <?php _e('Refresh folders','shortpixel-image-optimiser');?>
            </a>
        </div>


      <div class="searchbox">
            <form method="get">
                <input type="hidden" name="page" value="wp-short-pixel-custom" />
                <input type='hidden' name='order' value="<?php echo $this->order ?>" />
                <input type="hidden" name="orderby" value="<?php echo $this->orderby ?>" />

                <p class="search-form">
                  <label><?php _e('Search', 'shortpixel-image-optimiser'); ?></label>
                  <input type="text" name="s" value="<?php echo $this->search ?>" />

                </p>
                <?php //$customMediaListTable->search_box("Search", "sp_search_file");
                ?>
            </form>
      </div>
  </div>

  <div class='pagination tablenav'>
      <div class='tablenav-pages'>
        <?php echo $this->view->pagination; ?>
    </div>
  </div>

    <div class='list-overview'>
      <div class='heading'>
        <?php foreach($this->view->headings as $hname => $heading):
            $isSortable = $heading['sortable'];
        ?>
          <span class='heading <?php echo $hname ?>'>
              <?php echo $this->getDisplayHeading($heading); ?>
          </span>

        <?php endforeach; ?>
      </div>

        <?php if (count($this->view->items) == 0) : ?>
          <div class='no-items'> <p>
            <?php
            if ($this->search === false):
              echo(__('No images available. Go to <a href="options-general.php?page=wp-shortpixel-settings&part=adv-settings">Advanced Settings</a> to configure additional folders to be optimized.','shortpixel-image-optimiser'));
             else:
               echo __('Your search query didn\'t result in any images. ', 'shortpixel-image-optimiser');
            endif; ?>
          </p>
          </div>

        <?php endif; ?>

        <?php foreach($this->view->items as $item): ?>
        <div class='item item-C-<?php echo $item->id ?>'>
            <?php
              $itemFile = $fs->getFile($item->path);
              $filesize = $itemFile->getFileSize();
              $display_date = $this->getDisplayDate($item);

              $rowActions = $this->getRowActions($item, $itemFile);
              $actions = $this->getActions($item, $itemFile);
            ?>
            <span><div class='thumb'>
              <?php if ($filesize <= 500000 && $filesize > 0):
                $img_url = $fs->pathToUrl($itemFile);  ?>
                <img src="<?php echo $img_url ?>" />
              <?php endif; ?>

            </div></span>
            <span class='filename'><?php echo $itemFile->getFileName() ?>
                <div class="row-actions"><?php
                $numberActions = count($rowActions);
                for ($i = 0; $i < $numberActions; $i++)
                {
                    echo $rowActions[$i];
                    if ($i < ($numberActions-1) )
                      echo '|';
                }
                ?></div>
            </span>
            <span><?php echo (string) $itemFile->getFileDir(); ?></span>
            <span><?php echo $item->media_type ?></span>
            <span class="date"><?php echo $display_date ?></span>
            <span id='sp-cust-msg-C-<?php echo $item->id ?>'><?php echo $this->getDisplayStatus($item); ?></span>
            <span class='actions'>
              <?php echo $this->getDisplayActions($this->getActions($item, $itemFile))
            ?></span>
        </div>
        <?php endforeach; ?>
      </div>


      <div class='pagination tablenav bottom'>
        <div class='tablenav-pages'>
            <?php echo $this->view->pagination; ?>
        </div>
      </div>


</div> <!-- wrap -->
