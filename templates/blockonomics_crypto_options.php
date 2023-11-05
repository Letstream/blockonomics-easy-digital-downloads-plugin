<?php get_header();?>

<div id="blockonomics_checkout">
  <div class="bnomics-order-container">
    <div class="bnomics-select-container">
      <tr>
        <?php
        foreach ($context['cryptos'] as $code => $crypto) {
          
          $order_url = add_query_arg( array( 'crypto' => $code ), $context['order_url'] );
        ?>
          <a href="<?php echo $order_url;?>">
            <button class="bnomics-select-options button">
              <span class="bnomics-icon-<?php echo $code;?> bnomics-rotate-<?php echo $code;?>"></span>
              <span class="vertical-line">
                <?=__('Pay with', 'edd-blockonomics')?>
                <?php echo $crypto['name'];?>
              </span>
            </button>
          </a>
        <?php 
        }
        ?>
      </tr>
    </div>
  </div>
</div>

<?php get_footer();?>