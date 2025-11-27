<?php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('twentytwentyfive-child-style', get_stylesheet_uri());
}, 20);

add_action('template_redirect', function () {
    if (!is_admin() && !wp_doing_ajax() && !str_starts_with($_SERVER['REQUEST_URI'], '/wp-json')) {
			status_header(403);
			include get_404_template();
			exit;
    }
});

/* restric access */

add_action('init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_login = str_contains($uri, '/wp-login.php');
    $is_admin = str_contains($uri, '/wp-admin');

    $is_logged_in = is_user_logged_in();
    $has_secret = isset($_GET['secret']) && $_GET['secret'] === SECRET_LOGIN_SLUG;

    // Allow direct login via secret slug (with/without slash)
    if (trim($uri, '/') === SECRET_LOGIN_SLUG) {
        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    // 🚑 Allow redirect_to after login even if WP cookies not yet processed
    if (isset($_GET['redirect_to'])) {
        return;
    }

    // Let logged-in users go anywhere
    if ($is_logged_in) return;

    // Block direct access to login or admin pages
    if (($is_login || $is_admin) && !$has_secret) {
        status_header(404);
        include get_404_template();
        exit;
    }
});



// Allow WooCommerce REST API access
add_filter('woocommerce_rest_check_permissions', '__return_true', 10, 2);

// Add "Featured" checkbox to product categories
add_action('product_cat_add_form_fields', function() {
	?>
	<div class="form-field">
			<label for="featured"><?php _e('Featured Category', 'woocommerce'); ?></label>
			<input type="checkbox" name="featured" id="featured" value="1">
	</div>
	<?php
});

add_action('product_cat_edit_form_fields', function($term) {
	$featured = get_term_meta($term->term_id, 'featured', true);
	?>
	<tr class="form-field">
			<th scope="row" valign="top"><label for="featured"><?php _e('Featured Category', 'woocommerce'); ?></label></th>
			<td>
					<input type="checkbox" name="featured" id="featured" value="1" <?php checked($featured, 1); ?>>
			</td>
	</tr>
	<?php
});

// Save "Featured" field
function save_featured_category($term_id) {
	if (isset($_POST['featured'])) {
			update_term_meta($term_id, 'featured', 1);
	} else {
			delete_term_meta($term_id, 'featured');
	}
}
add_action('created_product_cat', 'save_featured_category');
add_action('edited_product_cat', 'save_featured_category');

// Include "Featured" field in REST API response
add_action('rest_api_init', function() {
	register_rest_field('product_cat', 'featured', [
			'get_callback' => function($term) {
					return get_term_meta($term['id'], 'featured', true) ? true : false;
			},
			'update_callback' => function($value, $term) {
					update_term_meta($term->term_id, 'featured', $value ? 1 : 0);
			},
			'schema' => [
					'description' => __('Is this a featured category?', 'woocommerce'),
					'type'        => 'boolean',
					'context'     => ['view', 'edit'],
			],
	]);
});
// Add custom checkbox to WooCommerce product data
function kangaroo_add_hot_offer_field() {
	global $post;
	$value = get_post_meta($post->ID, '_hot_offer', true);
	?>
	<div class="options_group">
			<p class="form-field">
					<label for="hot_offer"><?php esc_html_e('Hot Offer', 'kangaroo'); ?></label>
					<input type="checkbox" id="hot_offer" name="hot_offer" value="yes" <?php checked($value, 'yes'); ?>>
					<span class="description"><?php esc_html_e('Mark this product as a hot offer.', 'kangaroo'); ?></span>
			</p>
	</div>
	<?php
}
add_action('woocommerce_product_options_general_product_data', 'kangaroo_add_hot_offer_field');

// Save the field value
function kangaroo_save_hot_offer_field($post_id) {
	$hot_offer = isset($_POST['hot_offer']) ? 'yes' : 'no';
	update_post_meta($post_id, '_hot_offer', $hot_offer);
}
add_action('woocommerce_process_product_meta', 'kangaroo_save_hot_offer_field');

// Add a "Hot Offer" checkbox to Quick Edit
function add_hot_offer_quick_edit($column_name, $post_type) {
	if ($column_name !== 'hot_offer') return;

	?>
	<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
					<label class="alignleft">
							<input type="checkbox" name="hot_offer" value="yes">
							<span class="checkbox-title">Hot Offer</span>
					</label>
			</div>
	</fieldset>
	<?php
}
add_action('quick_edit_custom_box', 'add_hot_offer_quick_edit', 10, 2);

add_action('save_post_product', function ($post_id) {
	if (isset($_POST['hot_offer'])) {
		update_post_meta($post_id, '_hot_offer', 'yes');
	} else {
		update_post_meta($post_id, '_hot_offer', 'no');
	}
});

 /*
add_action('rest_api_init', function() {
	// Remove WP’s default CORS if needed
	remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

	// Add your headers
	add_filter('rest_pre_serve_request', function ($value) {
			header("Access-Control-Allow-Origin: https://ecommerce.tunca.site");
			header("Access-Control-Allow-Credentials: true");
			header("Access-Control-Expose-Headers: X-WP-Nonce, X-WC-Store-API-Nonce, Nonce"); 
			return $value;
	});
}, 15);
 */
add_filter('rest_pre_serve_request', function ($value) {
	$allowed_origins = [
			'http://localhost:3001',
			'http://localhost:3002',
			'http://localhost:3000',
			'http://127.0.0.1:3000',
			'http://127.0.0.1:3001',
			'http://127.0.0.1:3002',
			'https://ecommerce.tunca.site'
	];

	if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
			header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
			header("Access-Control-Allow-Methods: GET, OPTIONS");
			header("Access-Control-Allow-Headers: DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range");
			header("Access-Control-Expose-Headers: Content-Length,Content-Range");
	}

	// Special handling for image requests
	if (strpos($_SERVER['REQUEST_URI'], '/wp-content/uploads/') !== false) {
			header("Access-Control-Allow-Origin: *");
			header("Access-Control-Allow-Methods: GET, OPTIONS");
			header("Access-Control-Allow-Headers: DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range");
			header("Access-Control-Expose-Headers: Content-Length,Content-Range");
	}

	return $value;
}, 15);


function allow_user_registration_via_rest_api() {
	add_filter( 'rest_api_init', function() {
			register_rest_route('wp/v2', '/users', array(
					'methods' => 'POST',
					'callback' => 'rest_api_register_user',
					'permission_callback' => '__return_true' // Allow public access
			));
	});
}

function rest_api_register_user($request) {
	$params = $request->get_params();
	
	if (empty($params['username']) || empty($params['email']) || empty($params['password'])) {
			return new WP_Error('missing_fields', 'Username, email, and password are required.', array('status' => 400));
	}

	$user_id = wp_create_user($params['username'], $params['password'], $params['email']);

	if (is_wp_error($user_id)) {
			return $user_id;
	}

	return new WP_REST_Response(array('message' => 'User registered successfully.', 'user_id' => $user_id), 201);
}

/*** Webhhoks for categories */

add_action('init', 'allow_user_registration_via_rest_api');

function notify_nextjs_on_category_create($term_id) {
	error_log(" notify_nextjs_on_category_create triggered with term_id: " . $term_id);

	$category = get_term($term_id, 'product_cat');
	if (is_wp_error($category)) {
		error_log(" Error fetching term for creation.");
		return;
	}

	$thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
	$thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null;
	$featured = get_term_meta($term_id, 'featured', true);

	$category_data = [
		'id'          => $category->term_id,
		'name'        => $category->name,
		'slug'        => $category->slug,
		'description' => $category->description,
		'parent_id'   => $category->parent,
		'count'       => $category->count,
		'featured'    => $featured ? true : false,
		'image'       => $thumbnail_url,
		'custom_meta' => get_term_meta($term_id)
	];

	$webhook_url = defined('WC_FRONTEND_BASE_URL') ? WC_FRONTEND_BASE_URL . '/api/webhooks/categories' : null;
	$update_secret = defined('WC_CATEGORY_UPDATE_WEBHOOK_SECRET') ? WC_CATEGORY_UPDATE_WEBHOOK_SECRET : null;

	error_log(" Sending create webhook to: $webhook_url");
	send_nextjs_webhook($webhook_url, $category_data, $update_secret);
}


function notify_nextjs_on_category_update($term_id) {
	error_log(" notify_nextjs_on_category_update triggered with term_id: " . $term_id);

	$category = get_term($term_id, 'product_cat');
	if (is_wp_error($category)) {
		error_log(" Error fetching term for update.");
		return;
	}

	$thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
	$thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : null;
	$featured = get_term_meta($term_id, 'featured', true);

	$category_data = [
		'id'          => $category->term_id,
		'name'        => $category->name,
		'slug'        => $category->slug,
		'description' => $category->description,
		'parent_id'   => $category->parent,
		'count'       => $category->count,
		'featured'    => $featured ? true : false,
		'image'       => $thumbnail_url,
		'custom_meta' => get_term_meta($term_id)
	];

	$webhook_url = defined('WC_FRONTEND_BASE_URL') ? WC_FRONTEND_BASE_URL . '/api/webhooks/categories' : null;
	$update_secret = defined('WC_CATEGORY_UPDATE_WEBHOOK_SECRET') ? WC_CATEGORY_UPDATE_WEBHOOK_SECRET : null;

	error_log(" Sending update webhook to: $webhook_url");
	send_nextjs_webhook($webhook_url, $category_data, $update_secret);
}


function notify_nextjs_on_category_delete($term_id) {
	error_log(" notify_nextjs_on_category_delete triggered with term_id: " . $term_id);

	$webhook_url = defined('WC_FRONTEND_BASE_URL') ? WC_FRONTEND_BASE_URL . '/api/webhooks/categories' : null;
	$update_secret = defined('WC_CATEGORY_UPDATE_WEBHOOK_SECRET') ? WC_CATEGORY_UPDATE_WEBHOOK_SECRET : null;

	error_log(" Sending delete webhook to: $webhook_url");
	send_nextjs_webhook($webhook_url, ['id' => $term_id, 'deleted' => true], $update_secret);
}


function send_nextjs_webhook($url, $data, $secret) {
	if (!$url || !$secret) {
		error_log(" Webhook URL or secret is missing.");
		return;
	}

	$body = json_encode($data);
	$signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

	error_log(" Payload: " . $body);
	error_log(" Signature: " . $signature);

	$response = wp_remote_post($url, [
		'method'  => 'POST',
		'body'    => $body,
		'headers' => [
			'Content-Type'            => 'application/json',
			'X-WC-Webhook-Signature'  => $signature
		],
	]);

	if (is_wp_error($response)) {
		error_log(" Webhook request failed: " . $response->get_error_message());
	} else {
		error_log(" Webhook response code: " . wp_remote_retrieve_response_code($response));
		error_log(" Webhook response body: " . wp_remote_retrieve_body($response));
	}
}


add_filter('rest_user_query', function ($args, $request) {
	// Allow user listing only if the request is authenticated
	if (!current_user_can('list_users')) {
			return new WP_Error('rest_forbidden', __('Sorry, you are not allowed to list users.'), ['status' => 403]);
	}
	return $args;
}, 10, 2);


add_filter('rest_authentication_errors', function ($result) {
	if (!empty($result)) {
			return $result;
	}
	return true;
});

add_action('created_product_cat', 'notify_nextjs_on_category_create', 10, 1);
add_action('edited_product_cat', 'notify_nextjs_on_category_update', 10, 1);
add_action('delete_product_cat', 'notify_nextjs_on_category_delete', 10, 1);

add_filter( 'http_request_args', function( $args ) {
	$args['reject_unsafe_urls'] = false;
	return $args;
} );