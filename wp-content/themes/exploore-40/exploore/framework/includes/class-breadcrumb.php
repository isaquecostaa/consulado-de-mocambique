<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Breadcrumb class.
 *
 * @author Swlabs
 * @since 1.0
 */
class Slzexploore_Breadcrumb {

	/**
	 * Breadcrumb trail
	 *
	 * @var array
	 */
	private $crumbs = array();

	/**
	 * Add a crumb so we don't get lost
	 *
	 * @param string $name
	 * @param string $link
	 */
	public function add_crumb( $name, $link = '' ) {
		$this->crumbs[] = array(
			$name,
			$link
		);
	}

	/**
	 * Reset crumbs
	 */
	public function reset() {
		$this->crumbs = array();
	}

	/**
	 * Get the breadcrumb
	 *
	 * @return array
	 */
	public function get_breadcrumb() {
		return apply_filters( 'slzexploore_get_breadcrumb', $this->crumbs, $this );
	}

	/**
	 * Generate breadcrumb trail
	 *
	 * @return array of breadcrumbs
	 */
	public function generate() {
		$conditionals = array(
			'is_home',
			'is_404',
			'is_attachment',
			'is_single',
			'is_page',
			'is_post_type_archive',
			'is_category',
			'is_tag',
			'is_author',
			'is_date',
			'is_tax',
			'slzexploore_is_custom_post_type_archive',
		);
		if ( ( ! is_front_page() && ! ( is_post_type_archive() ) ) || is_paged() || slzexploore_is_custom_post_type_archive() ) {
			foreach ( $conditionals as $conditional ) {
				if ( call_user_func( $conditional ) ) {
					if( $conditional == 'slzexploore_is_custom_post_type_archive'){
						$crumbs_func = 'custom_post_type_archive';
					} else {
						$crumbs_func = substr( $conditional, 3 );
					}
					call_user_func( array( $this, 'add_crumbs_' . $crumbs_func ) );
					break;
				}
			}

			$this->search_trail();
			$this->paged_trail();

			return $this->get_breadcrumb();
		}

		return array();
	}

	/**
	 * is home trail
	 */
	private function add_crumbs_home() {
		$this->add_crumb( single_post_title( '', false ) );
	}

	/**
	 * 404 trail
	 */
	private function add_crumbs_404() {
		$this->add_crumb( esc_html__( 'Error 404', 'exploore' ) );
	}

	/**
	 * attachment trail
	 */
	private function add_crumbs_attachment() {
		global $post;

		$this->add_crumbs_single( $post->post_parent, get_permalink( $post->post_parent ) );
		$this->add_crumb( get_the_title(), get_permalink() );
	}

	/**
	 * Single post trail
	 *
	 * @param int    $post_id
	 * @param string $permalink
	 */
	private function add_crumbs_single( $post_id = 0, $permalink = '' ) {
		if ( ! $post_id ) {
			global $post;
		} else {
			$post = get_post( $post_id );
		}

		if ( 'post' != get_post_type( $post ) ) {
			$post_type = get_post_type_object( get_post_type( $post ) );
			$this->add_crumb( $post_type->labels->singular_name, get_post_type_archive_link( get_post_type( $post ) ) );
		} else {
			$cat = '';
			$post_options = get_post_meta( $post->ID, 'slzexploore_post_options', true);
			if ( isset( $post_options['main_category'] ) && !empty( $post_options['main_category'] ) ) {
				// Main category has seleted post options
				$cat = get_category_by_slug( $post_options['main_category'] );
			} else {
				$cat = current( get_the_category( $post ) );
			}
			if ( $cat ) {
				$this->term_ancestors( $cat->term_id, 'post_category' );
				$this->add_crumb( $cat->name, get_term_link( $cat ) );
			}
		}

		$this->add_crumb( get_the_title( $post ), $permalink );
	}

	/**
	 * Page trail
	 */
	private function add_crumbs_page() {
		global $post;

		if ( $post->post_parent ) {
			$parent_crumbs = array();
			$parent_id     = $post->post_parent;

			while ( $parent_id ) {
				$page          = get_post( $parent_id );
				$parent_id     = $page->post_parent;
				$parent_crumbs[] = array( get_the_title( $page->ID ), get_permalink( $page->ID ) );
			}

			$parent_crumbs = array_reverse( $parent_crumbs );

			foreach ( $parent_crumbs as $crumb ) {
				$this->add_crumb( $crumb[0], $crumb[1] );
			}
		}

		$this->add_crumb( get_the_title(), get_permalink() );
	}

	/**
	 * Post type archive trail
	 */
	private function add_crumbs_post_type_archive() {
		$post_type = get_post_type_object( get_post_type() );

		if ( $post_type ) {
			$this->add_crumb( $post_type->labels->singular_name, get_post_type_archive_link( get_post_type() ) );
		}
	}
	private function add_crumbs_custom_post_type_archive() {
		$post_type = get_post_type_object( get_post_type() );
		if ( $post_type ) {
			$this->add_crumb( $post_type->labels->singular_name, get_post_type_archive_link( get_post_type() ) );
		}
	}

	/**
	 * Category trail
	 */
	private function add_crumbs_category() {
		$this_category = get_category( $GLOBALS['wp_query']->get_queried_object() );

		if ( 0 != $this_category->parent ) {
			$this->term_ancestors( $this_category->parent, 'post_category' );
			$parent_category = get_category($this_category->parent );
			if ( ! is_wp_error( $parent_category ) && $parent_category ) {
				$this->add_crumb( $parent_category->name, get_category_link( $parent_category->term_id ) );
			}
		}

		$this->add_crumb( single_cat_title( '', false ), get_category_link( $this_category->term_id ) );
	}

	/**
	 * Tag trail
	 */
	private function add_crumbs_tag() {
		$queried_object = $GLOBALS['wp_query']->get_queried_object();
		$this->add_crumb( esc_html__( 'Posts tagged', 'exploore' ).sprintf( ' &ldquo;%s&rdquo;', single_tag_title( '', false ) ), get_tag_link( $queried_object->term_id ) );
	}

	/**
	 * Add crumbs for date based archives
	 */
	private function add_crumbs_date() {
		if ( is_year() || is_month() || is_day() ) {
			$this->add_crumb( get_the_time( 'Y' ), get_year_link( get_the_time( 'Y' ) ) );
		}
		if ( is_month() || is_day() ) {
			$this->add_crumb( get_the_time( 'F' ), get_month_link( get_the_time( 'Y' ), get_the_time( 'm' ) ) );
		}
		if ( is_day() ) {
			$this->add_crumb( get_the_time( 'd' ) );
		}
	}

	/**
	 * Add crumbs for date based archives
	 */
	private function add_crumbs_tax() {
		$this_term = $GLOBALS['wp_query']->get_queried_object();
		$taxonomy  = get_taxonomy( $this_term->taxonomy );

		$this->add_crumb( $taxonomy->labels->singular_name );

		if ( 0 != $this_term->parent ) {
			$this->term_ancestors( $this_term->parent, 'post_category' );
			$parent_term = get_term($this_term->parent, $this_term->taxonomy);
			if ( ! is_wp_error( $parent_term ) && $parent_term ) {
				$this->add_crumb( $parent_term->name, get_term_link( $this_term->parent, $this_term->taxonomy ) );
			}
		}

		$this->add_crumb( single_term_title( '', false ), get_term_link( $this_term->term_id, $this_term->taxonomy ) );
	}

	/**
	 * Add a breadcrumb for author archives
	 */
	private function add_crumbs_author() {
		global $author;

		$userdata = get_userdata( $author );
		$this->add_crumb( esc_html__( 'Author', 'exploore' ).': '.$userdata->display_name );
	}

	/**
	 * Add crumbs for a term
	 * @param string $taxonomy
	 */
	private function term_ancestors( $term_id, $taxonomy ) {
		$ancestors = get_ancestors( $term_id, $taxonomy );
		$ancestors = array_reverse( $ancestors );

		foreach ( $ancestors as $ancestor ) {
			$ancestor = get_term( $ancestor, $taxonomy );

			if ( ! is_wp_error( $ancestor ) && $ancestor ) {
				$this->add_crumb( $ancestor->name, get_term_link( $ancestor ) );
			}
		}
	}

	/**
	 * Add a breadcrumb for search results
	 */
	private function search_trail() {
		if ( is_search() ) {
			$this->add_crumb( esc_html__( 'Search results for', 'exploore' ).sprintf( ' &ldquo;%s&rdquo;' , get_search_query() ), remove_query_arg( 'paged' ) );
		}
	}

	/**
	 * Add a breadcrumb for pagination
	 */
	private function paged_trail() {
		if ( get_query_var( 'paged' ) ) {
			$this->add_crumb( esc_html__( 'Page', 'exploore' ).' '.get_query_var( 'paged' ) );
		}
	}
}