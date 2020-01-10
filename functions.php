<?php
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('parent-style')
    );
}

add_filter( 'wc_order_is_editable', '__return_true' );
// тест1
add_action( 'woocommerce_checkout_create_order', 'additional_hidden_checkout_field_save', 20, 2 );
function additional_hidden_checkout_field_save( $order, $data ) {
    if( ! isset($data['billing_phone']) ) return;

    if( ! empty($data['billing_phone']) ){
 //       $phone = str_replace([' ','-','_','(',')'],['','',''], $data['billing_phone']);
		$phone = preg_replace('/[^0-9.]+/', '', $data['billing_phone']);
        $phone = ltrim(ltrim($phone, '0'),'+');
        $formatted_phone = strlen($phone) <= 11 ? '380' . ltrim($phone, 0) : $phone;
		$formatted_phone = substr($formatted_phone, 2);
        // Set the formatted billing phone for the order
        $order->set_billing_phone( $formatted_phone );
    }
}

function order_declaration_backend ($order){
    echo "<p><strong>Статус посылки: ";
	  include_once "np.php";
    $np = new NovaPoshtaApi2('29e25f2b28d9f4aeba636f6789dc6e0d');
    $result = $np->documentsTracking($order->get_meta( '_novaposhta_field_data' ));
    echo(($result['data'][0]['Status']) . "</strong>");
}

add_action( 'woocommerce_admin_order_data_after_shipping_address', 'order_declaration_backend', 10, 1 );

add_filter( 'wp_mail_from_name', 'my_mail_from_name' );
function my_mail_from_name( $name ) {
    return "Informatica.com.ua";
}

// Add a the metabox to Order edit pages
add_action( 'add_meta_boxes', 'add_novaposhta_meta_box' );
function add_novaposhta_meta_box(){
    add_meta_box( 'novaposhta_field', __('Декларация НП','woocommerce'), 'add_novaposhta_meta_box_content', 'shop_order', 'side', 'core' );
}

function woocommerce_shop_order_search_order_total( $search_fields ) {
  $search_fields[] = '_novaposhta_field_data';
  return $search_fields;
}
add_filter( 'woocommerce_shop_order_search_fields', 'woocommerce_shop_order_search_order_total' );

// The metabox content
function add_novaposhta_meta_box_content(){
    global $post;
    $value = get_post_meta( $post->ID, '_novaposhta_field_data', true );
    echo '<input type="hidden" name="novaposhta_meta_field_nonce" value="' . wp_create_nonce() . '">
    <p style="border-bottom:solid 1px #eee;padding-bottom:13px;">
        <input type="text" style="width:250px;";" name="novaposhta_data_name" value="'.$value.'"></p>';
}

// Save the field value from metabox content
add_action( 'save_post_shop_order', 'save_novaposhta_meta_box_field_value', 10, 1 );
function save_novaposhta_meta_box_field_value( $post_id ) {
    if ( ! isset( $_POST[ 'novaposhta_meta_field_nonce' ] ) ) {
        return $post_id;
    }
    $nonce = $_REQUEST[ 'novaposhta_meta_field_nonce' ];
    if ( ! wp_verify_nonce( $nonce ) ) {
        return $post_id;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return $post_id;
    }
    if ( ! ( current_user_can( 'edit_shop_order', $post_id ) || current_user_can( 'edit_shop_order', $post_id ) ) ) {
        return $post_id;
    }
    $tmp = sanitize_text_field( $_POST[ 'novaposhta_data_name' ] );
    $tmp = preg_replace('/[^0-9.]+/', '', $tmp);
    update_post_meta( $post_id, '_novaposhta_field_data', $tmp );
}

// Display nova poshta tracking info and urls on customer email

add_action( 'woocommerce_email_after_order_table', 'add_novaposhta_tracking_to_customer_complete_order_email', 20, 4 );
function add_novaposhta_tracking_to_customer_complete_order_email( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $sent_to_admin )
            return; // Exit

    $novaposhta_value = $order->get_meta( '_novaposhta_field_data' );

    if ( ! empty($novaposhta_value) ) {
        $novaposhta_url = 'https://novaposhta.ua/tracking';
        $tracking_url = 'https://novaposhta.ua/tracking'.$novaposhta_value;
        $title        = __("Отследите свой заказ","woocommerce");
        $message      = '<p><strong>'. __("Декларация НП", "woocommerce").':</strong> ' . $novaposhta_value . '</p>
        <p>'. sprintf( __("Вы можете отследить Вашу посылку на %s или непосредственно из %s", "woocommerce"),
        '<a href="'.$novaposhta_url.'" target="_blank">'.__("novaposhta сайте", "woocommerce").'</a>',
        '<a href="'.$tracking_url.'" target="_blank">'.__("нашем сайте", "woocommerce").'</a>.</p>');
        echo '<style>
        .tracking table {width: 100%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;
            color: #737373; border: 1px solid #e4e4e4; margin-bottom:8px;}
        .tracking table td{text-align: left; border-top-width: 4px; color: #737373; border: 1px solid #e4e4e4;
            padding: 12px; padding-bottom: 4px;}
        </style>
        <div class="tracking">
        <h2>' . $title . '</h2>
        <table cellspacing="0" cellpadding="6">
            <tr><td>'.$message.'</td></tr>
        </table></div><br>';
    }
}


// Add SMS Center metabox
add_action( 'add_meta_boxes', 'sms_center_order_meta_boxes' );
function sms_center_order_meta_boxes() {
    add_meta_box(
        'woocommerce-order-verifyemail',
        __( 'SMS Center' ),
        'sms_center_order_meta_box_content',
        'shop_order',
        'side',
        'default'
    );
}

// SMS-Center metabox content
function sms_center_order_meta_box_content( $post ){
    $customeremail = get_post_meta( $post->ID, '_billing_email', true);
    $button_text1 = __( 'Заказ готов', 'woocommerce' );
    $button_text2 = __( 'Заказ отослан', 'woocommerce' );
	$button_text3 = __( 'Просим перезвонить', 'woocommerce' );
	$button_text4 = __( 'Рек-ты Н', 'woocommerce' );
	$button_text5 = __( 'Рек-ты Ч', 'woocommerce' );
	$button_text6 = __( 'Рек-ты О', 'woocommerce' );
	$button_text7 = __( 'Рек-ты ДД', 'woocommerce' );
	echo '<form method="post" action="CURRENT_FILE_URL">
        <input type="submit" name="submit_sms_center1" value="' . $button_text1 . '"/>
        <input type="hidden" name="sms_center_nonce1" value="' . wp_create_nonce() . '">';
    echo '<input type="submit" name="submit_sms_center2" value="' . $button_text2 . '"/>
        <input type="hidden" name="sms_center_nonce2" value="' . wp_create_nonce() . '">';
	echo '<input type="submit" name="submit_sms_center3" value="' . $button_text3 . '"/>
        <input type="hidden" name="sms_center_nonce3" value="' . wp_create_nonce() . '">';
	echo '<input type="submit" name="submit_sms_center4" value="' . $button_text4 . '"/>
        <input type="hidden" name="sms_center_nonce4" value="' . wp_create_nonce() . '">';
	echo '<input type="submit" name="submit_sms_center5" value="' . $button_text5 . '"/>
        <input type="hidden" name="sms_center_nonce5" value="' . wp_create_nonce() . '">';
	echo '<input type="submit" name="submit_sms_center6" value="' . $button_text6 . '"/>
        <input type="hidden" name="sms_center_nonce6" value="' . wp_create_nonce() . '">';
	echo '<input type="submit" name="submit_sms_center7" value="' . $button_text7 . '"/>
        <input type="hidden" name="sms_center_nonce7" value="' . wp_create_nonce() . '">
    </form>';
}

// Saving or doing an action when submitting
add_action( 'save_post', 'sms_center_save_meta_box_data' );
function sms_center_save_meta_box_data( $post_id ){
    // Only for shop order
    if ( 'shop_order' != $_POST[ 'post_type' ] )
        return $post_id;
	    // Checking that is not an autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return $post_id;
    // Check the user’s permissions (for 'shop_manager' and 'administrator' user roles)
    if ( ! current_user_can( 'edit_shop_order', $post_id ) && ! current_user_can( 'edit_shop_orders', $post_id ) )
        return $post_id;
    // Check if one of nonces is set (and our custom field)
    if ( ! isset( $_POST[ 'sms_center_nonce1' ] ) && isset( $_POST['submit_sms_center1'] ) ||
         ! isset( $_POST[ 'sms_center_nonce2' ] ) && isset( $_POST['submit_sms_center2'] ) ||
		 ! isset( $_POST[ 'sms_center_nonce3' ] ) && isset( $_POST['submit_sms_center3'] ) ||
		 ! isset( $_POST[ 'sms_center_nonce4' ] ) && isset( $_POST['submit_sms_center4'] ) ||
		 ! isset( $_POST[ 'sms_center_nonce5' ] ) && isset( $_POST['submit_sms_center5'] ) ||
		 ! isset( $_POST[ 'sms_center_nonce6' ] ) && isset( $_POST['submit_sms_center6'] ) ||
		 ! isset( $_POST[ 'sms_center_nonce7' ] ) && isset( $_POST['submit_sms_center7'] ) )
        return $post_id;
    $order = wc_get_order( $post_id );
	$order_mytotal = $order->get_subtotal()-$order->get_discount_total();
    // option for sending "zakaz gotov"
    if( isset( $_POST['submit_sms_center1'] ) ) {
	    $nonce1 = $_POST[ 'sms_center_nonce1' ];
    	if ( ! wp_verify_nonce( $nonce1 ) )
       	 	  return $post_id;
	    $message_sms = "Vash zakaz " . $order->id . " gotov k vidache. Summa: " . $order_mytotal . "hrn. Adres: Kiev, ul. Schuseva, d. 36. Ponedelnik-pyatnitsa 10.00-18.00, tel (068) 079-53-99, INFORMATICA.COM.UA";
		$message_email = "Добрый день!\n\nВаш заказ №". $order->id . " готов к выдаче.\nСумма к оплате: " . $order_mytotal ."грн\nВремя работы: с понедельника по пятницу 10.00-18.00\n\nС уважением, магазин informatica.com.ua,\n(068) 079-53-99";
		$title_email = "Ваш заказ готов к выдаче";
	}
    //     option for sending "zakaz otoslan"
    elseif( isset( $_POST['submit_sms_center2'] ) ) {
        $nonce2 = $_POST[ 'sms_center_nonce2' ];
        if ( ! wp_verify_nonce( $nonce2 ) )
            return $post_id;
        $message_sms = "Vash zakaz " . $order->id . " otpravlen! Nova Poshta, nomer deklaracii " . $order->get_meta( '_novaposhta_field_data' ) . ". INFORMATICA.COM.UA, tel (068) 079-53-99";
		$tracking_url = "https://novaposhta.ua/tracking/?cargo_number=" . $order->get_meta( '_novaposhta_field_data' );
		$message_email  = "Добрый день!\n\nВаш заказ №". $order->id . " отправлен.\n\nПеревозчик - Новая Почта. Декларация №" . $order->get_meta( '_novaposhta_field_data' ) . ".\nВы можете отследить состояние Вашей посылки на странице " . $tracking_url . "\n\nС уважением, магазин informatica.com.ua,\n(068) 079-53-99";
        $title_email = "Ваш заказ отослан, декларация №...";
	}
	    //     option for sending "prosim perezvonit"
    elseif( isset( $_POST['submit_sms_center3'] ) ) {
        $nonce3 = $_POST[ 'sms_center_nonce3' ];
        if ( ! wp_verify_nonce( $nonce3 ) )
            return $post_id;
		$message_sms = "Prosim perezvonit dlya podtverzhdeniya Vashego zakaza. Nomer zakaza: " . $order->id . ". INFORMATICA.COM.UA, tel (068) 079-53-99";
		$message_email = "Добрый день!\n\nПохоже, мы не можем дозвониться к Вам для подтверждения Вашего заказа.\nПросим перезвонить (068) 079-53-99.\n\nНомер Вашего заказа:". $order->id . ".\n\nС уважением, магазин informatica.com.ua";
		$title_email = "Не можем к Вам дозвониться";
	}
	    //     option for sending "реквизиты N "
    elseif( isset( $_POST['submit_sms_center4'] ) ) {
        $nonce4 = $_POST[ 'sms_center_nonce4' ];
        if ( ! wp_verify_nonce( $nonce4 ) )
            return $post_id;
		$message_sms = $order_mytotal . " hrn. 5169 3305 1223 4946 v PrivatBanke. Nezhigay Sergey. Prosim perezvonit posle oplati (068)079-5399!";
		$message_email = "Добрый день!\n\n" . $order_mytotal . " грн. 5169 3305 1223 4946 в Приватбанке. Нежигай Сергей. Просим перезвонить после оплаты (068)079-5399!\nНомер Вашего заказа:". $order->id . ".\n\nС уважением, магазин informatica.com.ua";
		$title_email = "Реквизиты для оплаты заказа в Информатике";
	}
		    //     option for sending "реквизиты Ч "
	    elseif( isset( $_POST['submit_sms_center5'] ) ) {
        $nonce5 = $_POST[ 'sms_center_nonce5' ];
        if ( ! wp_verify_nonce( $nonce5 ) )
            return $post_id;
		$message_sms = $order_mytotal . " hrn. 4149 6293 9828 9439 v PrivatBanke. Cherginets Sergey. Prosim perezvonit posle oplati (068)079-5399!";
		$message_email = "Добрый день!\n\n" . $order_mytotal . " грн. 4149 6293 9828 9439 в Приватбанке. Чергинец Сергей. Просим перезвонить после оплаты (068)079-5399!\nНомер Вашего заказа:". $order->id . ".\n\nС уважением, магазин informatica.com.ua";
		$title_email = "Реквизиты для оплаты заказа в Информатике";
	}
		    //     option for sending "реквизиты О "
	    elseif( isset( $_POST['submit_sms_center6'] ) ) {
        $nonce6 = $_POST[ 'sms_center_nonce6' ];
        if ( ! wp_verify_nonce( $nonce6 ) )
            return $post_id;
		$message_sms = $order_mytotal . " hrn. 5168 7422 2310 8519 v PrivatBanke. Olhovenko Andrey. Prosim perezvonit posle oplati (068)079-5399!";
		$message_email = "Добрый день!\n\n" . $order_mytotal . " грн. 5168 7422 2310 8519 в Приватбанке. Ольховенко Андрей. Просим перезвонить после оплаты (068)079-5399!\nНомер Вашего заказа:". $order->id . ".\n\nС уважением, магазин informatica.com.ua";
		$title_email = "Реквизиты для оплаты заказа в Информатике";
	}
		elseif( isset( $_POST['submit_sms_center7'] ) ) {
        $nonce7 = $_POST[ 'sms_center_nonce7' ];
        if ( ! wp_verify_nonce( $nonce7 ) )
            return $post_id;
		$message_sms = $order_mytotal . " hrn. 4149 4393 0021 7670 v PrivatBanke. Drin Dmitry. Prosim perezvonit posle oplati (068)079-5399!";
		$message_email = "Добрый день!\n\n" . $order_mytotal . " грн. 4149 4393 0021 7670 в Приватбанке. Дринь Дмитрий. Просим перезвонить после оплаты (068)079-5399!\nНомер Вашего заказа:". $order->id . ".\n\nС уважением, магазин informatica.com.ua";
		$title_email = "Реквизиты для оплаты заказа в Информатике";
	}
	else
		return $post_id;
	$customeremail = $order->get_billing_email();
	wp_mail($customeremail, $title_email, $message_email);
  include_once "smsc_api.php";
  $order = wc_get_order( $post_id );
  $r = send_sms(get_post_meta( $order->id, '_billing_phone', true ), $message_sms);
  if ($r[1] > 0){
        $order->add_order_note('Отослано SMS: '.$message_sms);
  }
  else{
        $order->add_order_note('SMS не отослано, ошибка'.$r[1]);
  }
}

/**
 *  Adds different colors to different order statuses in order list in admin
 */
add_action('admin_head', 'styling_admin_order_list' );
function styling_admin_order_list() {
    global $pagenow, $post;

    if( $pagenow != 'edit.php') return; // Exit
    if( get_post_type($post->ID) != 'shop_order' ) return; // Exit

    // HERE below set your custom statuses colors
    $order_status = 'on-hold'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #cc0099;
            color: #ffffff;
        }
    </style>
    <?php
    $order_status = 'processing'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #cc0099;
            color: #ffffff;
        }
    </style>
    <?php
	    $order_status = 'dozakaz'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #0000FF;
            color: #ffffff;
        }
    </style>
    <?php
	    $order_status = 'nalozhka'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #DC143C;
            color: #ffffff;
        }
    </style>
    <?php
		$order_status = 'direct-cherg'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #800080;
            color: #FFFF00;
        }
    </style>
    <?php
		$order_status = 'direct-mezhig'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #800080;
            color: #ffffff;
        }
    </style>
    <?php
			$order_status = 'check-payment'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #4682B4;
            color: #ffffff;
        }
    </style>
	<?php
			$order_status = 'ring1'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #00BFFF;
            color: #ffffff;
        }
    </style>
	<?php
			$order_status = 'ring2'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #00BFFF;
            color: #ffffff;
        }
    </style>
    <?php
			$order_status = 'to-sent-paid'; // <==== HERE
    ?>
    <style>
        .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
            background: #DC143C;
            color: #FFFF00;
        }
    </style>
    <?php
}


/**
 * Adds column headers to 'Orders' page immediately after 'Total' column.
 * Needed to show real order sum, without profit(delivery cost)
 * @param string[] $columns
 * @return string[] $new_columns
 */

function sv_wc_cogs_add_order_profit_column_header( $columns ) {

    $new_columns = array();

    foreach ( $columns as $column_name => $column_info ) {

        $new_columns[ $column_name ] = $column_info;

        if ( 'order_total' === $column_name ) {
            $new_columns['delivery_status'] = __( 'НП', 'my-textdomain' );
            $new_columns['order_profit'] = __( 'Сумма', 'my-textdomain' );
			$new_columns['order_item'] = __( 'Товары', 'my-textdomain' );
        }
    }

    return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'sv_wc_cogs_add_order_profit_column_header', 20 );

/**
 * Fills 'Sum' and 'Products' columns content to 'Orders' page immediately after 'Total' column.
 *
 * @param string[] $column name of column being displayed
 */
function sv_wc_cogs_add_order_profit_column_content( $column ) {
    global $post;

    if ( 'order_profit' === $column ) {

        $order    = wc_get_order( $post->ID );
        $currency = is_callable( array( $order, 'get_currency' ) ) ? $order->get_currency() : $order->order_currency;
        $profit   = '';
        $cost     = (float) $order->get_shipping_total();
        $total    = (float) $order->get_total();

        // don't check for empty() since cost can be '0'
        if ( '' !== $cost || false !== $cost ) {

            // now we can cast cost since we've ensured it was calculated for the order
            $cost   = (float) $cost;
            $profit = $total - $cost;
        }
		echo wc_price( $profit, array( 'currency' => $currency ));
	}
	elseif ( 'order_item' === $column ) {
        $order    = wc_get_order( $post->ID );
        $item   = '';
		$item = $order->get_items();
		foreach($item as $value){
			echo mb_strimwidth($value['name'], 0, 24, "..");
		}
    }
	elseif ( 'delivery_status' === $column ) {
		$order = wc_get_order( $post->ID );
        $declaration = $order->get_meta( '_novaposhta_field_data' );
		$declaration = preg_replace('/\s+/', '', $declaration);
		if (strlen($declaration)=== 14) {
		include_once "np.php";
        $np = new NovaPoshtaApi2('29e25f2b28d9f4aeba636f6789dc6e0d');
        $result = $np->documentsTracking($declaration);
		echo mb_strimwidth($result['data'][0]['Status'], 0, 24, "..");

		}

    }
}
add_action( 'manage_shop_order_posts_custom_column', 'sv_wc_cogs_add_order_profit_column_content' );

/**
 * Adjusts the styles for the new 'Profit' column.
 */
function sv_wc_cogs_add_order_profit_column_style() {

    $css = '.widefat .column-order_date, .widefat .column-order_profit { width: 5%; }';
    wp_add_inline_style( 'woocommerce_admin_styles', $css );
}
add_action( 'admin_print_styles', 'sv_wc_cogs_add_order_profit_column_style' );

/**
 * Remove product data tabs
 */
/*add_filter( 'woocommerce_product_tabs', 'woo_remove_product_tabs', 98 );

function woo_remove_product_tabs( $tabs ) {
    unset( $tabs['additional_information'] );  	// Remove the additional information tab
    return $tabs;
}
*/

add_filter( 'woocommerce_product_tabs', 'woo_remove_product_tabs', 98 );
function woo_remove_product_tabs( $tabs ) {
  if ( isset( $tabs['additional_information'] ) ) {
    unset( $tabs['additional_information'] );  	// Remove the additional information tab
  }
  return $tabs;
}




/**
 Skip Cart Page Go Straight to Checkout Page
 */
add_filter('add_to_cart_redirect', 'cw_redirect_add_to_cart');
function cw_redirect_add_to_cart() {
    global $woocommerce;
    $cw_redirect_url_checkout = $woocommerce->cart->get_checkout_url();
    return $cw_redirect_url_checkout;
}
/**
 Change button text to "Buy!"
 */
add_filter( 'woocommerce_product_single_add_to_cart_text', 'cw_btntext_cart' );
add_filter( 'woocommerce_product_add_to_cart_text', 'cw_btntext_cart' );
function cw_btntext_cart() {
    return __( 'Купить', 'woocommerce' );
}

add_filter( 'woocommerce_get_related_product_cat_terms', 'remove_related_product_categories', 10, 2 );
function remove_related_product_categories( $terms_ids, $product_id  ){
    return array();
}

/**
 * Join posts and postmeta tables
 *
 * @param string   $join
 * @param WP_Query $query
 *
 * @see https://iconicwp.com/blog/add-product-sku-woocommerce-search/
 *
 * @return string
 */
function iconic_product_search_join( $join, $query ) {
	if ( ! $query->is_main_query() || is_admin() || ! is_search() || ! is_woocommerce() ) {
		return $join;
	}

	global $wpdb;

	$join .= " LEFT JOIN {$wpdb->postmeta} iconic_post_meta ON {$wpdb->posts}.ID = iconic_post_meta.post_id ";

	return $join;
}

add_filter( 'posts_join', 'iconic_product_search_join', 10, 2 );

/**
 * Modify the search query with posts_where.
 *
 * @param string   $where
 * @param WP_Query $query
 *
 * @see https://iconicwp.com/blog/add-product-sku-woocommerce-search/
 *
 * @return string
 */
function iconic_product_search_where( $where, $query ) {
	if ( ! $query->is_main_query() || is_admin() || ! is_search() || ! is_woocommerce() ) {
		return $where;
	}

	global $wpdb;

	$where = preg_replace(
		"/\(\s*{$wpdb->posts}.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
		"({$wpdb->posts}.post_title LIKE $1) OR (iconic_post_meta.meta_key = '_sku' AND iconic_post_meta.meta_value LIKE $1)", $where );

	return $where;
}

add_filter( 'posts_where', 'iconic_product_search_where', 10, 2 );

add_filter( 'woocommerce_currencies', 'add_my_currency' );

function add_my_currency( $currencies ) {
     $currencies['UAH'] = __( 'Українська гривня', 'woocommerce' );
     return $currencies;
}

add_filter('woocommerce_currency_symbol', 'add_my_currency_symbol', 10, 2);

function add_my_currency_symbol( $currency_symbol, $currency ) {
     switch( $currency ) {
         case 'UAH': $currency_symbol = 'грн'; break;
     }
     return $currency_symbol;
}



add_filter( 'woocommerce_package_rates', 'custom_delivery_flat_rate_cost_calculation', 10, 2 );
function custom_delivery_flat_rate_cost_calculation( $rates, $package )
{
    // The total cart items  weight
    $cart_weight = WC()->cart->get_cart_contents_weight();
    foreach($rates as $rate_key => $rate_values){
        $rate_id = $rate_values->id;
		$rates[$rate_id]->cost = $cart_weight;
		            // Taxes rate cost (if enabled)
        $taxes = array();
 /*       foreach ($rates[$rate_key]->taxes as $key => $tax){
                $taxes[$key] = 55;
        }*/
        // Set the new taxes costs
        $rates[$rate_key]->taxes = $taxes;
	}
	return $rates;
}

add_filter('woocommerce_cart_shipping_method_full_label','remove_local_pickup_free_label', 10, 2);
function remove_local_pickup_free_label($full_label, $method){
    $full_label = substr($full_label, 0, strpos($full_label, ':'));
    return $full_label;
}

function wdm_remove_parent_category_from_url( $args ) {
    $args['rewrite']['hierarchical'] = false;
    return $args;
}

add_filter( 'woocommerce_taxonomy_args_product_cat', 'wdm_remove_parent_category_from_url' );

/**
 * add link to sms site in admin
 */
add_action( 'admin_bar_menu', 'make_parent_node', 999 );
function make_parent_node( $wp_admin_bar ) {
	$args = array(
		'id'     => 'new-post',     // id of the existing child node (New > Post)
		'title'  => 'SMS-send', // alter the title of existing node
		'href'  => 'https://sms-sms.com.ua/MyCampaign/ToOne/',
		'target' => '_blank',
		'parent' => false,          // set parent to false to make it a top level (parent) node
	);
	$wp_admin_bar->add_node( $args );
}



/**
 * add fields names for detailed info for drop shipping
 */
function order_phone_backend($order){
    echo get_post_meta( $order->id, '_billing_first_name', true )  . " " . get_post_meta( $order->id, '_billing_last_name', true )  . ", " . get_post_meta( $order->id, '_billing_phone', true ) ;
	if( get_post_meta( $order->id, '_billing_city', true ) ) echo ", НП: " . get_post_meta( $order->id, '_billing_city', true );
	if( get_post_meta( $order->id, '_billing_address_2', true ) ) echo " - отд:" . get_post_meta( $order->id, '_billing_address_2', true );
	echo "<br>----------------- ";
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'order_phone_backend', 10, 1 );

add_action( 'woocommerce_admin_order_totals_after_total', 'vp_add_sub_total2', 100, 1);
function vp_add_sub_total2( $order_id ) {
	$order = wc_get_order( $order_id );
	?><tr style="color: blue;">
	<td class="label">Сумма по товарам:</td>
	<td width="1%"></td>
	<td><?php echo wc_price($order->get_subtotal());?></td>
	</tr><?php
}

/*
 * сортировка и переименовывание полей в чеке
 */
add_filter( 'woocommerce_checkout_fields', 'awoohc_override_checkout_fields' );
function awoohc_override_checkout_fields( $fields ) {

   $fields['billing']['billing_email']['required'] = false;
   $fields['billing']['billing_last_name']['required'] = false;
   $fields['billing']['billing_first_name']['priority'] = 1;
   $fields['billing']['billing_last_name']['priority'] = 2;
   $fields['billing']['billing_phone']['priority'] = 30;
   $fields['billing']['billing_postcode']['priority'] = 75;
   $fields['billing']['billing_email']['priority'] = 110;

   $fields['billing']['billing_email']['required'] = false;
   $fields['billing']['billing_last_name']['required'] = false;
   $fields['billing']['billing_first_name']['required'] = false;
   $fields['billing']['billing_phone']['required'] = true;
   $fields['billing']['billing_city']['required'] = false;
   $fields['billing']['billing_address_1']['required'] = false;
   $fields['billing']['billing_address_2']['required'] = false;
   $fields['billing']['billing_postcode']['required'] = false;
   $fields['billing']['billing_last_name']['label'] = 'Имя';
   $fields['billing']['billing_first_name']['label'] = 'Фамилия';
   $fields['order']['order_comments']['placeholder']='';

   unset( $fields['billing']['billing_state'] );
   unset( $fields['billing']['billing_postcode'] );
   unset( $fields['billing']['billing_billing'] );
   return $fields;
}

add_filter( 'woocommerce_default_address_fields', 'custom_override_default_locale_fields' );
function custom_override_default_locale_fields( $fields ) {
    $fields['state']['priority'] = 5;
	$fields['city']['priority'] = 60;
    $fields['address_1']['priority'] = 90;
    $fields['address_2']['priority'] = 100;
	$fields['address_2']['label'] = 'Отделение Новой Почты';
	$fields['address_2']['required'] = 'false';
	$fields['address_2']['placeholder'] = '№ отделения';
    $fields['address_1']['placeholder'] = 'Укажите улицу, дом и квартиру';

    return $fields;
}

// Conditional Show hide checkout fields based on chosen shipping methods
add_action( 'wp_footer', 'conditionally_hidding_billing_company' );
function conditionally_hidding_billing_company(){
    // Only on checkout page
    if( ! is_checkout() ) return;

    // HERE your shipping methods rate ID "Home delivery"
    $home_delivery = 'local_pickup:12';
	$nova_delivery = 'flat_rate:16';
    ?>
    <script>
        jQuery(function($){
            // Choosen shipping method selectors slug
            var shipMethod = 'input[name^="shipping_method"]',
                shipMethodChecked = shipMethod+':checked';

            // Function that shows or hide imput select fields
            function showHide( actionToDo='show', selector='' ){
                if( actionToDo == 'show' )
                    $(selector).show( 200, function(){
           //             $(this).addClass("validate-required");
                    });
                else
                    $(selector).hide( 200, function(){
           //             $(this).removeClass("validate-required");
                    });
                $(selector).removeClass("woocommerce-validated");
                $(selector).removeClass("woocommerce-invalid woocommerce-invalid-required-field");
            }

            // Initialising: Hide if choosen shipping method is "Home delivery"
            if( $(shipMethodChecked).val() == '<?php echo $home_delivery; ?>' ){
                showHide('hide','#billing_city_field' );
			    showHide('hide','#billing_address_1_field' );
				showHide('hide','#billing_address_2_field' );
				}

            // Live event (When shipping method is changed)
            $( 'form.checkout' ).on( 'change', shipMethod, function() {
                if ( $(shipMethodChecked).val() == '<?php echo $home_delivery; ?>' ){
                    showHide('hide','#billing_city_field' );
				    showHide('hide','#billing_address_1_field' );
					showHide('hide','#billing_address_2_field' );
				}
                else if ( $(shipMethodChecked).val() == '<?php echo $nova_delivery; ?>' ){
                    showHide('show','#billing_city_field');
				    showHide('show','#billing_address_2_field');
					showHide('hide','#billing_address_1_field');
					}
					else {
                    	showHide('show','#billing_city_field');
				    	showHide('hide','#billing_address_2_field');
						showHide('show','#billing_address_1_field');
					}
            });
        });
    </script>
    <?php
}

add_action('woocommerce_available_payment_gateways', 'alter_shipping_methods');
function alter_shipping_methods($available_gateways){
	global $woocommerce;
	$chosen_titles = array();
	$available_methods = $woocommerce->shipping->get_packages();
	$chosen_rates = ( isset( $woocommerce->session ) ) ? $woocommerce->session->get( 'chosen_shipping_methods' ) : array();
	foreach ($available_methods as $method){
		foreach ($chosen_rates as $chosen) {
			if( isset( $method['rates'][$chosen] ) ) $chosen_titles[] = $method['rates'][ $chosen ]->label;
		}
		if( in_array( 'Самовывоз в Киеве (м. Сырец)', $chosen_titles ) ) {

		unset($available_gateways['paypal']);
		unset($available_gateways['bacs']);
//		unset($available_gateways['cod']);
		unset($available_gateways['cheque']);
		unset($available_gateways['liqpay']);

		}
		elseif( in_array( 'Отделение Новой Почты', $chosen_titles ) ) {
//		unset($available_gateways['paypal']);
//		unset($available_gateways['bacs']);
		unset($available_gateways['cod']);
//		unset($available_gateways['cheque']);
//		unset($available_gateways['liqpay']);
		}
		elseif( in_array( 'Курьером на адрес', $chosen_titles ) ) {
//		unset($available_gateways['bacs']);
		unset($available_gateways['cod']);
		unset($available_gateways['cheque']);
	}

  }
// Start: this part will remove some payment methonds (olhov) if there is product in order from definite category (memory)
	$unset = false;
	$category_ids = array( 163 );
	foreach ( $woocommerce->cart->cart_contents as $key => $values ) {
    	$terms = get_the_terms( $values['product_id'], 'product_cat' );
    	foreach ( $terms as $term ) {
        	if ( in_array( $term->term_id, $category_ids ) ) {
            $unset = true;
            break;
        	}
    	}
	}
    if ( $unset == true ) unset( $available_gateways['bacs'] );
    	else unset( $available_gateways['custom'] );
// End: this part removed some payment methonds (olhov) if there is product in order from definite category (memory)
  	return $available_gateways;
}


//function for show attr on product page
function cw_woo_attribute(){
    global $product;
    $attributes = $product->get_attributes();
    if ( ! $attributes ) {
        return;
    }

    $display_result = '';

    foreach ( $attributes as $attribute ) {


        if ( $attribute->get_variation() ) {
            continue;
        }
        $name = $attribute->get_name();
        if ( $attribute->is_taxonomy() ) {

            // $terms = wp_get_post_terms( $product->get_id(), $name, 'all' );
            //
            // $cwtax = $terms[0]->taxonomy;
            //
            // $cw_object_taxonomy = get_taxonomy($cwtax);
            //
            // if ( isset ($cw_object_taxonomy->labels->singular_name) ) {
            //     $tax_label = $cw_object_taxonomy->labels->singular_name;
            // } elseif ( isset( $cw_object_taxonomy->label ) ) {
            //     $tax_label = $cw_object_taxonomy->label;
            //     if ( 0 === strpos( $tax_label, 'Product ' ) ) {
            //         $tax_label = substr( $tax_label, 8 );
            //     }
            // }
            // $display_result .= $tax_label . ': ';
            // $tax_terms = array();
            // foreach ( $terms as $term ) {
            //     $single_term = esc_html( $term->name );
            //     array_push( $tax_terms, $single_term );
            // }
            // $display_result .= implode(', ', $tax_terms) .  '<br />';

        } else {
            $display_result .= $name . ': ';
            $display_result .= esc_html( implode( ', ', $attribute->get_options() ) ) . '<br />';
      //      var_dump($attribute);
        }
    }
    echo $display_result;
    echo $attributes["pa_warehouse"]. '::: ';
//    echo $attributes.
  //  var_dump($attributes);
}

add_action('woocommerce_single_product_summary', 'cw_woo_attribute', 25);
//839-1
?>
