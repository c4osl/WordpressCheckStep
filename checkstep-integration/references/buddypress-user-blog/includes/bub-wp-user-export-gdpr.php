<?php
/**
 * @package WordPress
 * @subpackage BuddyBoss Media
 */
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) )
	exit;

if ( ! class_exists( 'BUB_WP_User_Export_GDPR' ) ) {

	class BUB_WP_User_Export_GDPR {

		/**
		 * Constructor method.
		 */
		function __construct( $args = array() ) {

			add_filter(
				'wp_privacy_personal_data_exporters',
				array( $this, 'register_exporter' ),
				10
			);

			add_filter(
				'wp_privacy_personal_data_erasers',
				array( $this, 'erase_exporter' ),
				10
			);

		}

		function register_exporter( $exporters ) {
			$exporters['buddypress-user-blogs'] = array(
				'exporter_friendly_name' => __( 'BuddyPress User Blogs', 'bp-user-blog' ),
				'callback' => array( $this, 'blogs_exporter' ),
			);
			return $exporters;
		}

		function erase_exporter( $erasers ) {
			$erasers['buddypress-user-blogs'] = array(
				'eraser_friendly_name' => __( 'BuddyPress User Blogs', 'bp-user-blog' ),
				'callback'             => array( $this, 'blogs_eraser', 'bp-user-blog' ),
			);
			return $erasers;
		}

		function blogs_exporter( $email_address, $page = 1 ) {
			$per_page = 500; // Limit us to avoid timing out
			$page = (int) $page;

			$export_items = array();

			$user = get_user_by( 'email' , $email_address );
			if ( false === $user ) {
				return array(
					'data' => $export_items,
					'done' => true,
				);
			}

			$posts_details = $this->get_blogs( $user, $page, $per_page );
			$total = isset( $posts_details['total'] ) ? $posts_details['total'] : 0;
			$posts = isset( $posts_details['posts'] ) ? $posts_details['posts'] : array();

			if ( $total > 0 ) {
				foreach( $posts as $post ) {
					$item_id = "bub-post-{$post->ID}";

					$group_id = 'bub-posts';

					$group_label = __( 'BuddyPress User Blogs', 'bp-user-blog' );

					$permalink = get_permalink( $post->ID );

					// Plugins can add as many items in the item data array as they want
					$data = array(
						array(
							'name'  => __( 'Post Author', 'bp-user-blog' ),
							'value' => $user->display_name
						),
						array(
							'name'  => __( 'Post Author Email', 'bp-user-blog' ),
							'value' => $user->user_email
						),
						array(
							'name'  => __( 'Post Title', 'bp-user-blog' ),
							'value' => $post->post_title
						),
						array(
							'name'  => __( 'Post Content', 'bp-user-blog' ),
							'value' => $post->post_content
						),
						array(
							'name'  => __( 'Post Date', 'bp-user-blog' ),
							'value' => $post->post_date
						),
						array(
							'name'  => __( 'Post URL', 'bp-user-blog' ),
							'value' => $permalink
						),
					);

					$export_items[] = array(
						'group_id'    => $group_id,
						'group_label' => $group_label,
						'item_id'     => $item_id,
						'data'        => $data,
					);
				}
			}

			$offset = ( $page - 1 ) * $per_page;

			// Tell core if we have more comments to work on still
			$done = $total < $offset;
			return array(
				'data' => $export_items,
				'done' => $done,
			);
		}

		function get_blogs( $user, $page, $per_page ) {
			$pp_args   = array(
				'post_type'      => 'post',
				'post_status'      => 'any',
				'author'         => $user->ID,
				'posts_per_page' => $per_page,
				'paged'          => $page
			);
			$the_query = new WP_Query( $pp_args );
			if ( $the_query->have_posts() ) {
				return array( 'posts' => $the_query->posts, 'total' => $the_query->post_count );
			}
			return false;
		}


		function blogs_eraser( $email_address, $page = 1 ) {
			$per_page = 500; // Limit us to avoid timing out
			$page = (int) $page;

			$user = get_user_by( 'email' , $email_address );
			if ( false === $user ) {
				return array(
					'items_removed'  => false,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			}

			$items_removed  = false;
			$items_retained = false;
			$messages    = array();

			$items = $this->get_blogs( $user, 1, $per_page );

			if ( ! $items ) {
				return array(
					'items_removed'  => false,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			}

			$total	 = isset( $items['total'] ) ? $items['total'] : 0;
			$paged_posts	 = ! empty( $items['posts'] ) ? $items['posts'] : array();

			if ( $total ) {
				foreach ( (array) $paged_posts as $post ) {
					$attachments = get_posts( array(
						'post_type' => 'attachment',
						'posts_per_page' => -1,
						'post_parent' => $post->ID,
					) );

					if ( $attachments ) {
						foreach ( $attachments as $attachment ) {
							wp_delete_post( $attachment->ID, true );
						}
					}
					wp_delete_post( $post->ID, true );
					$items_removed = true;
				}
			}

			$offset = ( $page - 1 ) * $per_page;

			// Tell core if we have more comments to work on still
			$done = $total < $offset;

			return array(
				'items_removed'  => $items_removed,
				'items_retained' => $items_retained,
				'messages'       => $messages,
				'done'           => $done,
			);
		}

	}

	new BUB_WP_User_Export_GDPR();

}