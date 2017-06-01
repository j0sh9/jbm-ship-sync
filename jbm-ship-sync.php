<?php
/*
Plugin Name: _Shipping Sync
Description: Check processing orders against shipped orders
Version: 1.0
*/

function jb_shipping_sync_report() {
	$jb_page_title = 'Shipping Sync';
	$jb_menu_title = 'Shipping Sync';
	$jb_capability = 'manage_affiliates';
	$jb_menu_slug = 'jb-shipping-sync-report';
	$jb_callback = 'jb_shipping_sync_report_html';
	$jb_icon_url = 'dashicons-welcome-view-site';
	$jb_menu_position = 120;
	add_menu_page(  $jb_page_title,  $jb_menu_title,  $jb_capability,  $jb_menu_slug,  $jb_callback,  $jb_icon_url,  $jb_menu_position );
}

function jb_shipping_sync_report_html() {
?>
<style>
	.jbm_shipstation_sync {
		display: flex;
	}
	.jbm_shipstation_sync div {
		width: 50%;
	}
</style>


<?php
// in main file

    if (isset($_POST['jbm_ss_store_id'])) {
        update_option('jbm_ss_store_id', $_POST['jbm_ss_store_id']);
        $jbm_ss_store_id = $_POST['jbm_ss_store_id'];
    } 
    if (isset($_POST['jbm_ss_api_key'])) {
        update_option('jbm_ss_api_key', $_POST['jbm_ss_api_key']);
        $jbm_ss_api_key = $_POST['jbm_ss_api_key'];
    } 
    if (isset($_POST['jbm_ss_api_secret'])) {
        update_option('jbm_ss_api_secret', $_POST['jbm_ss_api_secret']);
        $jbm_ss_api_secret = $_POST['jbm_ss_api_secret'];
    } 

    $jbm_ss_store_id = get_option('jbm_ss_store_id');
    $jbm_ss_api_key = get_option('jbm_ss_api_key');
    $jbm_ss_api_secret = get_option('jbm_ss_api_secret');
	
	$now = current_time( 'mysql' );
	$before = date( 'Y-m-d', strtotime($now.' +1 days') );
	if (isset($_POST['jbm_ss_end_date'])) {
		$before = $_POST['jbm_ss_end_date'];
	}
	$after = date('Y-m-d', strtotime($now.' -1 days'));
	if (isset($_POST['jbm_ss_start_date'])) {
		$after = $_POST['jbm_ss_start_date'];
	}
	if ( empty($jbm_ss_store_id) || empty($jbm_ss_api_key) || empty($jbm_ss_api_secret) ) :
	?>
<h1>ShipStation Orders Not Synced</h1>
<form method="POST">
    <label for="jbm_ss_store_id">Store ID
    <input type="text" name="jbm_ss_store_id" id="jbm_ss_store_id" value="<?php echo $jbm_ss_store_id; ?>"></label><br>
    <label for="jbm_ss_api_key">API Key
    <input type="text" name="jbm_ss_api_key" id="jbm_ss_api_key" value="<?php echo $jbm_ss_api_key; ?>" style="width:50%;"></label><br>
    <label for="jbm_ss_api_secret">API Secret
    <input type="text" name="jbm_ss_api_secret" id="jbm_ss_api_secret" value="<?php echo $jbm_ss_api_secret; ?>" style="width:50%;"></label><br>
    <input type="submit" value="Save" class="button button-primary button-large">
</form>
<hr>
<?php
	else:
?>
<form method="POST">
    <label for="jbm_ss_start_date">Start Date
    <input type="date" name="jbm_ss_start_date" id="jbm_ss_start_date" value="<?php echo $after; ?>"></label><br>
    <label for="jbm_ss_end_date">End Date
    <input type="date" name="jbm_ss_end_date" id="jbm_ss_end_date" value="<?php echo $before; ?>"></label><br>
    <input type="submit" value="Search" class="button button-primary button-large">
</form>
<hr>
<div class="jbm_shipstation_sync">
<div>
<h2>TEST = Processing / Shipstation = Shipped</h2>
<?php
	
	$auth = base64_encode($jbm_ss_api_key.':'.$jbm_ss_api_secret);
	$storeId = $jbm_ss_store_id;

	$ss_before = urlencode($before);
	$ss_after = urlencode($after);
	
	$shipment_ids = array();
	
	$pages = 1;
	$page = 1;
		
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, "https://ssapi.shipstation.com/shipments?pageSize=500&storeId=$storeId&createDateStart=$ss_after&createDateEnd=$ss_before&page=$page");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);

	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	  "Authorization: Basic $auth"
	));

	$shipments_response = curl_exec($ch);
	curl_close($ch);

	$shipments = json_decode($shipments_response);
	echo $shipments->total." ShipStation Shipments found.<br>";
	$pages = $shipments->pages;
	$shipments = $shipments->shipments;
	
	foreach( $shipments as $shipment ) {
		$shipment_ids[] = $shipment->orderNumber;
	}
	
	if ( $pages > 1 ) {
		while ( $page <= $pages ) {
			$page++;
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, "https://ssapi.shipstation.com/shipments?pageSize=500&storeId=$storeId&createDateStart=$ss_after&createDateEnd=$ss_before&page=$page");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "Authorization: Basic $auth"
			));

			$shipments_response = curl_exec($ch);
			curl_close($ch);

			$shipments = json_decode($shipments_response);
			$shipments = $shipments->shipments;

			foreach( $shipments as $shipment ) {
				$shipment_ids[] = $shipment->orderNumber;
			}
		}
	}

	$pages = 1;
	$page = 1;	
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, "https://ssapi.shipstation.com/orders?pageSize=500&storeId=$storeId&createDateStart=$ss_after&createDateEnd=$ss_before&orderStatus=shipped&page=$page");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);

	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	  "Authorization: Basic $auth"
	));

	$orders_response = curl_exec($ch);
	curl_close($ch);

	$shipment_orders = json_decode($orders_response);
	echo $shipment_orders->total." ShopStation Orders Shipped found.<br>";
	$pages = $shipment_orders->pages;
	$shipment_orders = $shipment_orders->orders ;
	
	foreach( $shipment_orders as $shipment_order ) {
		if ( ! in_array($shipment_order->orderNumber, $shipment_ids ) ) {
			$shipment_ids[] = $shipment_order->orderNumber;
		}
	}
	
	if ( $pages > 1 ) {
		while ( $page <= $pages ) {
			$page++;
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, "https://ssapi.shipstation.com/orders?pageSize=500&storeId=$storeId&createDateStart=$ss_after&createDateEnd=$ss_before&orderStatus=shipped&page=$page");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "Authorization: Basic $auth"
			));

			$orders_response = curl_exec($ch);
			curl_close($ch);

			$shipment_orders = json_decode($orders_response);
			$shipment_orders = $shipment_orders->orders ;

			foreach( $shipment_orders as $shipment_order ) {
				if ( ! in_array($shipment_order->orderNumber, $shipment_ids ) ) {
					$shipment_ids[] = $shipment_order->orderNumber;
				}
			}
		}
	}
	
	echo count($shipment_ids)." total ShipStation shipped orders found.<br>";
	
	$sync_orders = get_posts( array(
		'numberposts' => -1,
		'meta_key'    => '',
		'meta_value'  => '',
		'post_type'   => array( 'shop_order' ),
		'post_status' => array( 'wc-pending','wc-on-hold','wc-processing' ),
		'date_query' => array(
			'after' => $after,
			'before' => $before,
		)

	) );
	
	echo count($sync_orders)." uncompleted Store orders found.<br>";
	$i = 0;
	$matched_orders = '';
	foreach ( $sync_orders as $sync_order ) {
		$order = wc_get_order( $sync_order );
		$order_id = $order->get_id();
		if ( in_array( $order_id, $shipment_ids ) ) {
			$i++;
			$matched_orders .= "$i - <a href='/wp-admin/post.php?post=".$order_id."&action=edit' target='_blank'>".$order_id."</a><br>";
		}
	}
	echo "<h3>$i orders shipped and not marked complete in store.</h3>";
	echo $matched_orders;
?>
</div>


<div>
<h2>Store = Processing / Shipstation = On Hold or Awaiting Payment</h2>
<?php
	
	$hold_ids = array();

	$pages = 1;
	$page = 1;	
	
	$status_options = array('awaiting_payment','on_hold');
	
	foreach( $status_options as $status_option ) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, "https://ssapi.shipstation.com/orders?pageSize=500&storeId=$storeId&createDateStart=$ss_after&createDateEnd=$ss_before&orderStatus=$status_option&page=$page");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		  "Authorization: Basic $auth"
		));

		$orders_response = curl_exec($ch);
		curl_close($ch);

		$shipment_orders = json_decode($orders_response);
		echo $shipment_orders->total." $status_option ShipStation orders found.<br>";
		$pages = $shipment_orders->pages;
		$shipment_orders = $shipment_orders->orders ;

		foreach( $shipment_orders as $shipment_order ) {
			if ( ! in_array($shipment_order->orderNumber, $hold_ids ) ) {
				$hold_ids[] = $shipment_order->orderNumber;
			}
		}

		if ( $pages > 1 ) {
			while ( $page <= $pages ) {
				$page++;
				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, "https://ssapi.shipstation.com/orders?pageSize=500&storeId=$storeId&createDateStart=$ss_after&createDateEnd=$ss_before&orderStatus=$status_option&page=$page");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HEADER, FALSE);

				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				  "Authorization: Basic $auth"
				));

				$orders_response = curl_exec($ch);
				curl_close($ch);

				$shipment_orders = json_decode($orders_response);
				$shipment_orders = $shipment_orders->orders ;

				foreach( $shipment_orders as $shipment_order ) {
					if ( ! in_array($shipment_order->orderNumber, $hold_ids ) ) {
						$hold_ids[] = $shipment_order->orderNumber;
					}
				}
			}
		}
	}
	
	echo count($hold_ids)." total ".implode(', ',$status_options)." ShipStation orders found.<br>";
	
	$sync_orders = get_posts( array(
		'numberposts' => -1,
		'meta_key'    => '',
		'meta_value'  => '',
		'post_type'   => array( 'shop_order' ),
		'post_status' => array( 'wc-processing' ),
		'date_query' => array(
			'after' => $after,
			'before' => $before,
		)

	) );
	
	echo count($sync_orders)." processing orders found in Store.<br>";
	$i = 0;
	$matched_orders = '';
	foreach ( $sync_orders as $sync_order ) {
		$order = wc_get_order( $sync_order );
		$order_id = $order->get_id();
		if ( in_array( $order_id, $hold_ids ) ) {
			$i++;
			$matched_orders .= "$i - <a href='/wp-admin/post.php?post=".$order_id."&action=edit' target='_blank'>".$order_id."</a><br>";
		}
	}
	echo "<h3>$i orders processing in Store not set to ship in ShipStation.</h3>";
	echo $matched_orders;
?>
</div>
</div>
<?php
	endif;
}

add_action( 'admin_menu', 'jb_shipping_sync_report' );
