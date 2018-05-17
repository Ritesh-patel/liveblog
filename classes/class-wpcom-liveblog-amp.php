<?php

/**
 * Class WPCOM_Liveblog_AMP
 *
 * Adds AMP support for Liveblog
 */
class WPCOM_Liveblog_AMP {

	/**
	 * Called by WPCOM_Liveblog::load(),
	 */
	public static function load() {

		// Make sure AMP plugin is installed, if not exit.
		if ( ! function_exists( 'amp_activate' ) ) {
			return;
		}

		// Hook at template_redirect level as some Liveblog hooks require it.
		add_filter( 'template_redirect', array( __CLASS__, 'setup' ), 10 );

		// Add a /pagination to URLs to allow for pagination in AMP.
		add_filter( 'init', array( __CLASS__, 'add_endpoint_for_pagination' ), 10 );
		add_filter( 'init', array( __CLASS__, 'add_endpoint_for_single_entry' ), 10 );
	}

	/**
	 * AMP Setup by removing and adding new hooks.
	 *
	 * @return void
	 */
	public static function setup() {
		// If we're on an AMP page then bail.
		if ( ! is_amp_endpoint() ) {
			return;
		}

		// Remove the standard Liveblog markup which just a <div> for React to render.
		remove_filter( 'the_content', array( 'WPCOM_Liveblog', 'add_liveblog_to_content' ), 20 );

		// Remove standard Liveblog scripts as custom JS is not required for AMP.
		remove_action( 'wp_enqueue_scripts', array( 'WPCOM_Liveblog', 'enqueue_scripts' ) );

		// Remove WordPress adding <p> tags as breaks layout.
		remove_filter( 'the_content', 'wpautop' );

		add_filter( 'amp_post_template_metadata', array( __CLASS__, 'append_liveblog_to_metadata' ), 10, 2 );

		// Add AMP ready markup to post.
		add_filter( 'the_content', array( __CLASS__, 'append_liveblog_to_content' ), 7 );

		// Add AMP CSS for Liveblog.
		// If this an AMP Theme then use enqueue for styles.
		if ( current_theme_supports( 'amp' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		} else {
			add_action( 'amp_post_template_css', array( __CLASS__, 'print_styles' ) );
		}
	}

	/**
	 * Add Endpoint for pagination description
	 */
	public static function add_endpoint_for_pagination() {
		add_rewrite_endpoint( 'pagination', EP_PERMALINK, true );
	}

	/**
	 * Add Endpoint for pagination description
	 */
	public static function add_endpoint_for_single_entry() {
		add_rewrite_endpoint( 'single', EP_PERMALINK, true );
	}

	/**
	 * Print styles out by including file.
	 *
	 * @return void
	 */
	public static function print_styles() {
		include dirname( __DIR__ ) . '/assets/amp.css';
	}

	/**
	 * Enqueue Styles
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		wp_enqueue_style( 'liveblog', plugin_dir_url( __DIR__ ) . 'assets/amp.css' );
	}

	public static function append_liveblog_to_metadata( $metadata, $post ) {

		// If we are not viewing a liveblog post then exist the filter.
		if ( WPCOM_Liveblog::is_liveblog_post( $post->ID ) === false ) {
			return $data;
		}

		$request = self::get_request_data();

		$entries = WPCOM_Liveblog::get_entries_paged( $request->page, $request->last_known_entry );

		// Set the last known entry for users who don't have one yet.
		if ( $request->last_known_entry === false ) {
			$request->last_known_entry = $entries['entries'][0]->id . '-' . $entries['entries'][0]->timestamp;
		}

		$blog_updates = [];

		foreach ( $entries['entries'] as $key => $entry ) {
			//$amp_content                           = self::prepare_entry_content( $entry->content, $entry );
			//$entries['entries'][$key]->amp_content = $amp_content->get_amp_content();

			//var_dump( $entry);

			//get publisher info

			$publisher_name = $metadata['publisher']['name'];
			$publisher_organization = $metadata['publisher']['type'];

			$blog_item = (object)array(
				'@type'         => 'Blog Posting',
				'headline'      => 'headline',
				'url'           => $entry->share_link,
				'datePublished' => date( "yyyy - mm - dd", $entry->entry_time ),
				'articleBody'   => (object) array(
					'@type'     => 'Text',
				),
				'publisher'	    => (object) array(
					'@type'     => $publisher_organization,
					'name'	    => $publisher_name,
				),
			);

			array_push( $blog_updates, $blog_item );

		}

			// var_dump( $blog_updates );

			// die();

		$metadata['liveBlogUpdate'] = $blog_updates;

		return $metadata;
	}

	/**
	 * Append Liveblog to Content
	 *
	 * @param  string $content WordPress Post Content.
	 * @return string          Updated Content
	 */
	public static function append_liveblog_to_content( $content ) {
		global $post;

		if ( WPCOM_Liveblog::is_liveblog_post( $post->ID ) === false ) {
			return $data;
		}

		$request = self::get_request_data();

		if ( 'single' === $request->type ) {
			$single_entry = get_comment( $request->id );

			$content .= self::get_template(
				'entry', array(
					'content'    => $single_entry->comment_content,
					'authors'    => $single_entry->comment_author,
					'time'       => $single_entry->entry_time,
					'date'       => $single_entry->date,
					'time_ago'   => $single_entry->time_ago,
					'share_link' => $single_entry->share_link,
				)
			);

			//$single_entry = WPCOM_Liveblog::get_single_entry( $request->id );

			//$single_entry = WPCOM_Liveblog::get_entries_paged( $request->page, $request->id );

			//var_dump($single_entry);

			// $content .= self::get_template(
			// 	'entry', array(
			// 		'content'    => $entry->content,
			// 		'authors'    => $entry->authors,
			// 		'time'       => $entry->entry_time,
			// 		'date'       => $entry->date,
			// 		'time_ago'   => $entry->time_ago,
			// 		'share_link' => $entry->share_link,
			// 	)
			// );

			return $content;
		}

		$entries = WPCOM_Liveblog::get_entries_paged( $request->page, $request->last_known_entry );

		// Set the last known entry for users who don't have one yet.
		if ( $request->last_known_entry === false ) {
			$request->last_known_entry = $entries['entries'][0]->id . '-' . $entries['entries'][0]->timestamp;
		}

		$content .= self::get_template(
			'feed', array(
				'entries'  => self::filter_entries( $entries['entries'], $post->post_id ),
				'page'     => $entries['page'],
				'pages'    => $entries['pages'],
				'links'    => self::get_pagination_links( $request, $entries['pages'], $post->post_id ),
				'settings' => array(
					'entries_per_page' => WPCOM_Liveblog_Lazyloader::get_number_of_entries(),
					'refresh_interval' => WPCOM_Liveblog::get_refresh_interval()
				)
			)
		);

		return $content;
	}

	/**
	 * Filter Entries, adding Time Ago, and Entry Date.
	 *
	 * @param  array $entries Entries.
	 * @return array         Updates Entries
	 */
	public static function filter_entries( $entries, $post_id ) {
		$permalink = amp_get_permalink( $post_id );

		foreach ( $entries as $key => $entry ) {
			$entries[ $key ]->time_ago  = self::get_entry_time_ago( $entry );
			$entries[ $key ]->date      = self::get_entry_date( $entry );
			$entries[ $key ]->permalink = self::build_single_entry_permalink( $permalink, $entry->id );
		}

		return $entries;
	}

	/**
	 * Work out Entry time ago.
	 *
	 * @param  object $entry Entry.
	 * @return string        Time Ago
	 */
	public static function get_entry_time_ago( $entry ) {
		return human_time_diff( $entry->entry_time, current_time( 'timestamp', true ) ) . ' ago';
	}

	/**
	 * Work out Entry date.
	 *
	 * @param  object $entry Entry.
	 * @return string        Date
	 */
	public static function get_entry_date( $entry ) {
		$utc_offset  = get_option( 'gmt_offset' ) . 'hours';
		$date_format = get_option( 'date_format' );

		return date_i18n( $date_format, strtotime( $utc_offset, $entry->entry_time ) );
	}

	/**
	 * Gets Pagination Links (First, Last, Next, Previous)
	 *
	 * @param  object $request Request Object.
	 * @param  int    $pages   Number of pages.
	 * @param  int    $post_id Post ID.
	 * @return object         Pagination Links
	 */
	public static function get_pagination_links( $request, $pages, $post_id ) {
		$links = array();

		$permalink = amp_get_permalink( $post_id );

		$links['first'] = self::build_paged_permalink( $permalink, 1, $request->last_known_entry );
		$links['last']  = self::build_paged_permalink( $permalink, $pages, $request->last_known_entry );

		$links['prev'] = false;
		if ( $request->page > 1 ) {
			$links['prev'] = self::build_paged_permalink( $permalink, $request->page - 1, $request->last_known_entry );
		}

		$links['next'] = false;
		if ( $request->page < $pages ) {
			$links['next'] = self::build_paged_permalink( $permalink, $request->page + 1, $request->last_known_entry );
		}

		return (object) $links;
	}

	/**
	 * Builds up a pagination link.
	 *
	 * @param  string $permalink        Permalink.
	 * @param  int    $page             Page Number.
	 * @param  string $last_known_entry Last Know Entry.
	 * @return string                   Pagination Link
	 */
	public static function build_paged_permalink( $permalink, $page, $last_known_entry ) {
		return $permalink . '/pagination/page/' . $page . '/entry/' . $last_known_entry;
	}

	/**
	 * Builds up a pagination link.
	 *
	 * @param  string $permalink        Permalink.
	 * @param  int    $page             Page Number.
	 * @param  string $last_known_entry Last Know Entry.
	 * @return string                   Pagination Link
	 */
	public static function build_single_entry_permalink( $permalink, $id ) {
		return $permalink . '/single/' . $id;
	}

	/**
	 * Get Page and Last known entry from the request.
	 *
	 * @return object Request Data.
	 */
	public static function get_request_data() {
		$pagination       = get_query_var( 'pagination' );

		if ( empty( $pagination ) ) {
			$pagination = get_query_var( amp_get_slug() );
		}

		if ( 'pagination' === substr( $pagination, 0, strlen( 'pagination' ) ) ) {
			return self::get_pagination_request( $pagination );
		} else if( 'single' == substr( $pagination, 0, strlen( 'single' ) ) ) {
			return self::get_single_request( $pagination );
		}
	}

	public static function get_pagination_request( $query_var ) {
		$page             = preg_match( '/page\/(\d*)/', $query_var, $matches ) ? (int) $matches[1] : 1;
		$last_known_entry = preg_match( '/entry\/([\d-]*)/', $query_var, $matches ) ? $matches[1] : false;

		return (object) array(
			'page'             => $page,
			'last_known_entry' => $last_known_entry,
		);
	}

	public static function get_single_request( $query_var ) {
		$page = preg_match( '/single\/(\d*)/', $query_var, $matches ) ? (int) $matches[1] : null;

		return (object) array(
			'type' => 'single',
			'id'   => $page,
		);
	}

	/**
	 * Get template.
	 *
	 * @param  string $name      Name of Template.
	 * @param  array  $variables Variables to be passed to Template.
	 * @return string            Rendered Template
	 */
	public static function get_template( $name, $variables = array() ) {
		$template = new WPCOM_Liveblog_AMP_Template();
		return $template->render( $name, $variables );
	}
}
