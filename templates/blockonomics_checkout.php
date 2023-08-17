<?php
/** 
 * Blockonomics Checkout Page
 * 
 * The following variables are available to be used in the template along with all WP/EDD Functions/Methods/Globals
 * 
 * To prevent namespace conflicts, all the variables below are wrapped in an array "$context" which can be used to access
 * the required key bwlo
 * 
 * $order: Order Object
 * $order_id: EDD Order ID
 * $order_amount: Crypto Amount
 * $crypto: Crypto Object (code, name, uri) e.g. (btc, Bitcoin, bitcoin)
 * $payment_uri: Crypto URI with Amount and Protocol
 * $crypto_rate_str: Conversion Rate of Crypto to Fiat. Please see comment on php/Blockonomics.php -> get_crypto_rate_from_params() on rate difference.
 */
?>

<?php get_header();?>

<div id="blockonomics_checkout">
    <div class="bnomics-order-container">

        <!-- Spinner -->
        <div class="bnomics-spinner-wrapper">
            <div class="bnomics-spinner"></div>
        </div>

        <!-- Display Error -->
        <div class="bnomics-display-error">
            <h2><?= __('Display Error', 'blockonomics-bitcoin-payments') ?></h2>
            <p><?= __('Unable to render correctly, Note to Administrator: Please try enabling other modes like No Javascript or Lite mode in the Blockonomics plugin > Advanced Settings.', 'blockonomics-bitcoin-payments') ?></p>
        </div>

        <!-- Blockonomics Checkout Panel -->
        <div class="bnomics-order-panel">
            <table>
                <tr>
                    <th class="bnomics-header">
                        <!-- Order Header -->
                        <span class="bnomics-order-id">
                            <?= __('Order #', 'blockonomics-bitcoin-payments') ?><?php echo $context['order_id']; ?>
                        </span>

                        <div>
                            <span class="blockonomics-icon-cart"></span>
                            <?php echo number_format((float)$context['order']['value'], 2, '.', '') ?> <?php echo $context['order']['currency'] ?>
                        </div>
                    </th>
                </tr>
            </table>
            <table>
                <tr>
                    <th>
                        <!-- Order Address -->
                        <label class="bnomics-address-text"><?= __('To pay, send', 'blockonomics-bitcoin-payments') ?> <?php echo strtolower($context['crypto']['name']); ?> <?= __('to this address:', 'blockonomics-bitcoin-payments') ?></label>
                        <label class="bnomics-copy-address-text"><?= __('Copied to clipboard', 'blockonomics-bitcoin-payments') ?></label>
                        <div class="bnomics-copy-container">
                            <input type="text" value="<?php echo $context['address']; ?>" id="bnomics-address-input" readonly />
                            <span id="bnomics-address-copy" class="blockonomics-icon-copy"></span>
                            <span id="bnomics-show-qr" class="blockonomics-icon-qr"></span>
                        </div>

                        <div class="bnomics-qr-code">
                            <div class="bnomics-qr">
                                <a href="<?php echo $context['payment_uri']; ?>" target="_blank" class="bnomics-qr-link">
                                    <canvas id="bnomics-qr-code"></canvas>
                                </a>
                            </div>
                            <small class="bnomics-qr-code-hint">
                                <a href="<?php echo $context['payment_uri']; ?>" target="_blank" class="bnomics-qr-link"><?= __('Open in wallet', 'blockonomics-bitcoin-payments') ?></a>
                            </small>
                        </div>

                    </th>
                </tr>
            </table>
            <table>
                <tr>
                    <th>
                        <label class="bnomics-amount-text"><?= __('Amount of', 'blockonomics-bitcoin-payments') ?> <?php echo strtolower($context['crypto']['name']); ?> (<?php echo strtoupper($context['crypto']['code']); ?>) <?= __('to send:', 'blockonomics-bitcoin-payments') ?></label>
                        <label class="bnomics-copy-amount-text"><?= __('Copied to clipboard', 'blockonomics-bitcoin-payments') ?></label>

                        <div class="bnomics-copy-container" id="bnomics-amount-copy-container">
                            <input type="text" value="<?php echo $context['order_amount']; ?>" id="bnomics-amount-input" readonly />
                            <span id="bnomics-amount-copy" class="blockonomics-icon-copy"></span>
                            <span id="bnomics-refresh" class="blockonomics-icon-refresh"></span>
                        </div>

                        <small class="bnomics-crypto-price-timer">
                            1 <?php echo strtoupper($context['crypto']['code']); ?> = <span id="bnomics-crypto-rate"><?php echo $context['crypto_rate_str']; ?></span> <?php echo $context['order']['currency']; ?>, <?= __('updates in', 'blockonomics-bitcoin-payments') ?> <span class="bnomics-time-left">00:00 min</span>
                        </small>
                    </th>
                </tr>

            </table>
        </div>
    </div>
</div>

<script>
    <?php echo $context['script']; ?>
</script>

<?php get_footer();?>