<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
?>

<div class="woocommerce-order">

        <?php if ( $order ) : ?>

                <?php if ( $order->has_status( 'failed' ) ) : ?>

                        <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php _e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ); ?></p>

                        <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
                                <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php _e( 'Pay', 'woocommerce' ) ?></a>
                                <?php if ( is_user_logged_in() ) : ?>
                                        <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php _e( 'My account', 'woocommerce' ); ?></a>
                                <?php endif; ?>
                        </p>

                <?php else : ?>

                        <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Спасибо. Ваш заказ был принят.', 'woocommerce' ), $order ); ?></p>

                        <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

                                <li class="woocommerce-order-overview__order order">
                                        <?php _e( 'Номер заказа:', 'woocommerce' ); ?>
                                        <strong><?php echo $order->get_order_number(); ?></strong>
                                </li>

                                <li class="woocommerce-order-overview__date date">
                                        <?php _e( 'Дата:', 'woocommerce' ); ?>
                                        <strong><?php echo date_i18n( get_option( 'date_format' ), $order->get_date_created() ); ?></strong>
                                </li>

<!--                              <li class="woocommerce-order-overview__total total">
                                        <?php _e( 'Total:', 'woocommerce' ); ?>
                                        <strong><?php echo $order->get_formatted_order_total(); ?></strong>
                                </li>
-->
                        </ul>

                <?php endif; ?>


                <?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
                <?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

        <?php else : ?>

                <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Спасибо! Мы получили Ваш заказ.', 'woocommerce' ), null ); ?></p>

        <?php endif; ?>

</div>
