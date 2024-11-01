<?php
/*
Plugin Name: WP SHORTSCORE
Description: Show off your SHORTSCORES in a review box at the end of your posts.
Plugin URI:  http://shortscore.org
Version:     8.0
Text Domain: wp-shortscore
Domain Path: /language
Author:      Marc Tönsing
Author URI:  http://marc.tv
License URI: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Class WpShortscore
 */
class WpShortscore {
	private $version = '7.0';
	private $whitelist = array(
		"Dreamcast",
		"Switch",
		"GameBoy",
		"iOS",
		"Android",
		"GameCube",
		"Wii",
		"Super Nintendo",
		"Mega Drive",
		"NES",
		"Vita",
		"GameBoy",
		"GBA",
		"SNES",
		"PlayStation",
		"macOS",
		"Windows",
		"XBOX",
		"PC",
		"PSP"
	);

	/**
	 * WpShortscore constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'wan_load_textdomain' ) );

		$this->frontendInit();

		if ( is_admin() ) {
			$this->frontendAdminInit();
			add_action( 'save_post', array( $this, 'saveUserInput' ) );
			add_action( 'add_meta_boxes', array( $this, 'shortscore_custom_meta' ) );
			//add_action( 'admin_notices', array( $this, 'wp_shortscore_message' ) );
		}
	}

	/**
	 * Load textdomain
	 */
	public function wan_load_textdomain() {
		load_plugin_textdomain( 'wp-shortscore', false, dirname( plugin_basename( __FILE__ ) ) . '/language/' );
	}

	/**
	 * Initialise frontend methods
	 */
	public function frontendInit() {
		add_action( 'wp_print_styles', array( $this, 'enqueScripts' ) );
		add_filter( 'the_content', array( $this, 'appendShortscore' ), 99 );
	}

	public function frontendAdminInit() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueAdminScripts' ) );
	}

	/*
	 * helper method to save meta data to a post.
	 * */
	public function savePostMeta( $post_ID, $meta_name, $meta_value ) {
		add_post_meta( $post_ID, $meta_name, $meta_value, true ) || update_post_meta( $post_ID, $meta_name, $meta_value );
	}

	/**
	 * Pull Shortscore data by using the shortscore id and save it to the post.
	 *
	 * @param $post_id
	 *
	 */
	public function saveUserInput( $post_id ) {
		// Checks save status
		$is_autosave    = wp_is_post_autosave( $post_id );
		$is_revision    = wp_is_post_revision( $post_id );
		$is_valid_nonce = ( isset( $_POST['shortscore_nonce'] ) && wp_verify_nonce( $_POST['shortscore_nonce'], basename( __FILE__ ) ) ) ? 'true' : 'false';

		// Exits script depending on save status
		if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
			return;
		}

		// Get the author's nickname
		$post_author_id  = get_post_field( 'post_author', $post_id );
		$author_nickname = get_the_author_meta( 'nickname', $post_author_id );

		// Checks for input and sanitizes/saves if needed
		$title                 = $this->getPostData( '_shortscore_game_title' );
		$shortscore_userrating = $this->getPostData( '_shortscore_user_rating' );
		$shortscore_summary    = $this->getPostData( '_shortscore_summary' );


		if ( function_exists( 'get_post_meta' ) ) {

			// JSON structure (defined as array)
			$result = [
				'game' => [
					'id'    => - 1,
					'url'   => get_permalink(),
					'title' => $title,
					'count' => 0
				],

				'shortscore' => [
					'userscore' => $shortscore_userrating,
					'url'       => get_permalink(),
					'author'    => $author_nickname,
					'summary'   => $shortscore_summary,
					'date'      => get_the_date( DateTime::ISO8601 ),
					'id'        => - 1
				],
			];

			$result_obj = json_decode(json_encode($result));

			if ( $title != '' OR $shortscore_userrating != '' ) {
				$this->savePostMeta( $post_id, '_shortscore_result', $result_obj );
				$this->savePostMeta( $post_id, '_shortscore_user_rating', $shortscore_userrating );
			}

			if ( isset( $_POST['delete_shortscore'] ) ) {
				delete_post_meta( $post_id, '_shortscore_result' );
			}

			$msg_code = 'success';

			add_filter( 'redirect_post_location', function ( $location ) use ( $msg_code ) {
				return add_query_arg( 'wp-shortscore-msg', $msg_code, $location );
			} );
		}

		return;
	}

	/**
	 * Display Shortscore review box below a post
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function appendShortscore( $content ) {
		if ( is_single() ) {
			$post_id = get_the_ID();
			if ( metadata_exists( 'post', $post_id, '_shortscore_result' ) ) {
				$content = $content . $this->getShortscoreHTML();
			}
		}

		// Returns the content.
		return $content;
	}

	/**
	 * Load CSS in the theme for the SHORTSCORE styling.
	 */
	public function enqueScripts() {
		if ( is_single() && get_post_meta( get_the_ID(), '_shortscore_result', true ) != '' ) {
			wp_enqueue_style(
				"shortscore-base", plugins_url( 'css/shortscore-base.css', __FILE__ ), $this->version );

			wp_enqueue_script(
				"shortscore-scripts", plugins_url( 'js/jquery.shortscore.js', __FILE__), array('jquery'), $this->version, true);

			wp_enqueue_style(
				"shortscore-rating", plugins_url( 'css/shortscore-rating.css', __FILE__ ), true, $this->version );
		}
	}

	/**
	 * Load CSS in the admin backend for the SHORTSCORE styling.
	 */
	public function enqueAdminScripts() {

		wp_enqueue_style(
			"shortscore-base", plugins_url( 'css/shortscore-base.css', __FILE__ ), $this->version );

		wp_enqueue_style(
			"shortscore-rangeslider", plugins_url( 'rangeslider/rangeslider.css', __FILE__ ), $this->version );

		wp_enqueue_style(
			"shortscore-rating", plugins_url( 'css/shortscore-rating.css', __FILE__ ), array(), $this->version );

		wp_enqueue_script(
			'shortscore-rangeslider', plugins_url( 'rangeslider/rangeslider.min.js', __FILE__ ), array( "jquery" ), $this->version );

		wp_enqueue_script(
			'shortscore-rangeslider-init', plugins_url( 'rangeslider/rangeslider.init.js', __FILE__ ), array(
			"jquery",
			"shortscore-rangeslider"
		), $this->version );


	}

	/**
	 * Adds a meta box to the post editing screen
	 */
	public function shortscore_custom_meta() {
		add_meta_box( 'shortscore_meta', __( 'Add SHORTSCORE', 'wp-shortscore' ), array(
			$this,
			'shortscore_meta_callback'
		), 'post', 'advanced', 'high' );
	}

	public function object_to_array( $d ) {
		if ( is_object( $d ) ) {
			$d = get_object_vars( $d );
		}

		return is_array( $d ) ? array_map( __METHOD__, $d ) : $d;
	}


	/**
	 * Outputs the content of the meta box
	 */
	public function shortscore_meta_callback( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'shortscore_nonce' );
		$shortscore_stored_meta = get_post_meta( $post->ID );
		$title = '';
		$shortscore = '';
		$shortscore_summary = '';

		if ( isset ( $shortscore_stored_meta['_shortscore_result'] ) ) {
			$result = ( get_post_meta( $post->ID, '_shortscore_result', true ));

			if ( isset( $result->shortscore ) AND isset( $result->shortscore->userscore ) ) {
				$shortscore = $result->shortscore->userscore;
			}

			if ( isset( $result->shortscore ) AND isset( $result->shortscore->summary ) ) {
				$shortscore_summary = $result -> shortscore ->summary;
			}

			if ( property_exists ( $result ,'game' ) AND property_exists ( $result->game,'title' ) ){
				$title = $result->game->title;
			}
		}

		if ( $shortscore == '' ) {
			$shortscore = 0;
		}

		$html = '';

		$slug = '_shortscore_game_title';
		$html .= '<p class="rangeslider-box">
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( 'Game title', 'wp-shortscore' ) . '</label><br>
                    <input type="text" name="' . $slug . '" id="' . $slug . '" value="' . $title . '"/>
                </p>';

		$slug = '_shortscore_summary';
		$html .= '<p>
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( 'Summary', 'wp-shortscore' ) . '</label><br>
                    <textarea name="' . $slug . '" id="' . $slug . '" class="widefat" cols="50" rows="5">' . $shortscore_summary . '</textarea>
                </p>';

		$slug = '_shortscore_user_rating';
		$html .= '<p>
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( 'Shortscore (1 to 10)', 'wp-shortscore' ) . '</label><br>
                    <input type="range" min="0" max="10" step ="0.5" name="' . $slug . '" id="' . $slug . '" value="' . $shortscore . '"/>
                </p>';


		echo $html;

		echo $this->getShortscoreHTML();

	}

	public function getPostData( $slug ) {

		if ( isset( $_POST[ $slug ] ) ) {
			$data = sanitize_text_field( $_POST[ $slug ] );

			return $data;
		} else {
			return false;
		}
	}


	public function renderInputField( $post, $slug, $label, $type = '' ) {

		$shortscore_stored_meta = get_post_meta( $post->ID );

		if ( isset ( $shortscore_stored_meta[ $slug ] ) ) {
			$value = $shortscore_stored_meta[ $slug ][0];
		}
		$html = '<p>
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( $label, "wp-shortscore" ) . '</label>
                    <input type="text" name="' . $slug . '" id="' . $slug . '" value="' . $value . '"/>
                </p>';

		return $html;

	}

	/**
	 * Admin notices
	 **/
	public function wp_shortscore_message() {
		if ( array_key_exists( 'wp-shortscore-error', $_GET ) ) { ?>
            <div class="error ">
            <p>
				<?php
				switch ( $_GET['wp-shortscore-error'] ) {
					case 'shortscore-id':
					case 'result-null':
						_e( 'This SHORTSCORE ID does not exist', 'wp-shortscore' );
						break;
					default:
						echo __( 'An error occurred when saving the SHORTSCORE:' ) . ' [' . $_GET['wp-shortscore-error'] . ']';
						break;
				}
				?>
            </p>
            </div><?php

		}

		if ( array_key_exists( 'wp-shortscore-msg', $_GET ) ) { ?>
            <div class="update notice notice-success is-dismissible">
            <p>
				<?php
				switch ( $_GET['wp-shortscore-msg'] ) {
					case 'success':
						_e( 'Valid SHORTSCORE ID found and data saved to post successfully', 'wp-shortscore' );
						break;
					default:
						_e( 'SHORTSCORE ID saved', 'wp-shortscore' );
						break;
				}
				?>
            </p>
            </div><?php

		}
	}


private function getPlatforms($post_id){
		$platforms = array();
		$tags = wp_get_post_tags($post_id);

		foreach ($tags as $tag) {
			foreach ($this->whitelist as $os) {
				if ( stripos($tag->name,$os) !== false ) {
					$platforms[] = $tag->name;
				}
			}
		}

		return $platforms;

}


private function getShortscoreJSON(){
	$blogimage_url = '';
	$blogimage_width = '';
	$blogimage_height = '';
	$post_id = get_the_ID();
	$result = get_post_meta( $post_id, '_shortscore_result', true );
	$domain = get_site_url();
	$pid = $post_id;
	$game_title = $result->game->title;
	$post_title = get_the_title($post_id);
	$author_id = get_post_field( 'post_author', $post_id );
	$author_name = $result->shortscore->author;
	$author_url = get_author_posts_url($author_id);
	$shortscore = $result->shortscore->userscore;
	$url = get_permalink($post_id);
	$date_zulu = $result->shortscore->date;
	$shortscore_summary = nl2br( $result->shortscore->summary );
	$arr_plattforms = $this->getPlatforms($post_id);
	$local_code = get_locale();

	$custom_logo_id = get_theme_mod( 'custom_logo' );
	$bloglogo = wp_get_attachment_image_src( $custom_logo_id , 'full' );
	if($bloglogo !== false){
		$blogimage_url = $bloglogo[0];
		$blogimage_width = $bloglogo[1];
		$blogimage_height = $bloglogo[2];
	}

  $featuredimage_id = get_post_thumbnail_id($pid);
	$gameimage = wp_get_attachment_image_src($featuredimage_id, 'full' );
	if($gameimage !== false){
		$gameimage_url = $gameimage[0];
		$gameimage_width = $gameimage[1];
		$gameimage_height = $gameimage[2];
	}

	$blogname = get_bloginfo( 'name' );

	$arr = array(
	'@context' => 'https://schema.org',
	'@graph' => array(
		'itemReviewed' => array(
			'name' => $game_title,
			'@type' => 'VideoGame',
			'applicationCategory' => 'Game',
			'operatingSystem' => $arr_plattforms,
			'logo' => array(
				'@type' => 'ImageObject',
				'url' => $gameimage_url,
				'width' => $gameimage_width,
				'height' => $gameimage_height,
			),
		),
	  '@type' => 'Review',
	  '@id' => $domain.'/?p='.$pid,
	  'name' => $post_title,
	  'author' => array(
			'@type' => 'Person',
	    '@id' => $domain.'/?author='.$author_id,
	    'name' => $author_name,
	    'sameAs' => $author_url
	  ),
		'publisher' => array (
			'@type' => 'Organisation',
			'name' => $blogname,
			'sameAs' => $domain,
			'logo' => array(
				'@type' => 'ImageObject',
				'url' => $blogimage_url,
				'width' => $blogimage_width,
				'height' => $blogimage_height,
			),
		),
	  'reviewRating' => array(
	    '@type' => 'Rating',
	    'ratingValue' => $shortscore,
	    'bestRating' => '10',
	    'worstRating' => '1'
	  ),
	  'url' => $url,
	  'datePublished' => $date_zulu,
	  'description' => $shortscore_summary,
		'inLanguage' => $local_code
	)
	);
	$json = json_encode($arr,JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
	$json_markup = '<script type="application/ld+json">' . $json . '</script>';

	return $json_markup;

}

	/**
	 * @return float|string
	 */
	private function getShortscoreHTML() {
		$shortscore = '';
		$post_id    = get_the_ID();

		if ( get_post_meta( $post_id, '_shortscore_result', true ) != '' ) {

			$result = get_post_meta( $post_id, '_shortscore_result', true );

			if(! is_object($result) ){
				$result = json_decode(json_encode($result));
			}

			$shortscore_url = get_permalink();
			$shortscore = $result->shortscore->userscore;
			$shortscore_summary = nl2br( $result->shortscore->summary );
			$shortscore_author = $result->shortscore->author;
			$shortscore_title  = $result->game->title;
			$shortscore_date   = $result->shortscore->date;
		}

		if ( $shortscore == '' OR $shortscore < 1 ) {
			$shortscore_class = 0;
		} else {
			$shortscore_class = round($shortscore);
		}

		$notice = '';

		if ( is_admin() ) {

			$notice       = '';
			$notice_inner = '';

			if ( $shortscore == '' OR $shortscore < 1 ) {

				$notice_inner .= '<li>' . __( 'The SHORTSCORE needs to be greater than zero.', 'wp-shortscore' ) . '</li>';
				$shortscore   = 0;
			}

			if ( isset($shortscore_summary) && $shortscore_summary == '' ) {
				$notice_inner .= '<li>' . __( 'Summary field is empty.', 'wp-shortscore' ) . '</li>';
			}

			if ( isset($shortscore_title) && $shortscore_title == '' ) {
				$notice_inner .= '<li>' . __( 'Game title field is emtpy.', 'wp-shortscore' ) . '</li>';
			}

			if ( $notice_inner != '' ) {
				$notice .= '<div class="shortscore-notice">';
				$notice .= '<p><strong>' . __( 'Attention:', 'wp-shortscore' ) . '</strong></p>';
				$notice .= '<ul>';
				$notice .= $notice_inner;
				$notice .= '</ul></div>';

			}

			echo '<h2>' . __( 'Preview', 'wp-shortscore' ) . '</h2>';
		}


		/* HTML */
		$shortscore_html = '<div class="type-game">';
		// $shortscore_html .= '<h3 class="shortscore-title"><a class="score" href="' . $shortscore_url . '">' . __( 'Rating on SHORTSCORE.org', 'wp-shortscore' ) . '</a></h3>';
		$shortscore_html .= '<div class="shortscore-hreview">';

		if ( isset($shortscore_summary) && $shortscore_summary != '' ) {
			$shortscore_html .= '<div class="text">';
			$shortscore_html .= '<span class="item"> <a class="score" href="' . $shortscore_url . '"><strong class="fn">' . $shortscore_title . '</strong></a>: </span>';
			$shortscore_html .= '<span class="summary">' . $shortscore_summary . '</span><span class="reviewer vcard"> – <span class="fn">' . $shortscore_author . '</span></span>';
			$shortscore_html .= '</div>';
		}

		$shortscore_html .= '<div class="rating">';
		$shortscore_html .= '<div id="shortscore_value" class="shortscore shortscore-' . $shortscore_class . '"><span class="value">' . $shortscore . '</span></div>';
		$shortscore_html .= '<div class="outof">' . sprintf( __( 'out of %s.', 'wp-shortscore' ), '<span class="best">10</span>' ) . '</div>';

		if ( isset($shortscore_date) ){
			$shortscore_html .= '<span class="dtreviewed">' . $shortscore_date . '</span> ';
		}

		$shortscore_html .= '</div>';

		//$shortscore_html .= '<div class="link"><a href="' . $shortscore_url . '">' . sprintf( __( '%s', 'wp-shortscore' ), '<span class="votes">' . sprintf( _n( 'one user review', '%s user reviews', $shortscore_count, 'wp-shortscore' ), $shortscore_count ) . '</span> ' ) . __( 'on', 'wp-shortscore' ) . ' SHORTSCORE.org ' . __( 'to', 'wp-shortscore' ) . ' ' . $shortscore_title . '</a></div>';

		$shortscore_html .= '</div>';
		$shortscore_html .= '</div>';

		if ( is_admin() ) {

			$buttons = '<p style="color:darkred;"><label for="delete_shortscore">' . __("Check to delete this SHORTSCORE",'shortscore') . '</label> <input id="delete_shortscore" name="delete_shortscore" type="checkbox" value="1"/></p>';

			$shortscore_html = '<div class="shortscore-preview">' . $shortscore_html . '</div>' . $notice . $buttons;

		}

		if ( is_admin() OR ( $shortscore_url != '' AND $shortscore_title != '' AND $shortscore_author != '' AND $shortscore_date != '' AND $shortscore != '' AND $shortscore_summary != '' ) ) {
			$json_markup = $this->getShortscoreJSON();
			return $shortscore_html . $json_markup;
		} else {
			return false;
		}

	}

}

new WpShortscore();
