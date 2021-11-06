<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block to show post types and their counts.
	 * Also shows 5 posts created between 9AM to 5PM with category baz and tag foo.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content The block content, if any.
	 * @param WP_Block $block The instance of this block.
	 *
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$current_post_id      = get_the_ID();
		$post_types           = get_post_types( [ 'public' => true ] );
		$wrapper_markup       = '<div %1$s><ul>%2$s</ul><p>%3$s</p>%4$s</div>';
		$post_types_markup    = '';
		$foo_baz_posts_markup = '';

		foreach ( $post_types as $post_type_slug ) {
			$post_type_object = get_post_type_object( $post_type_slug );
			$count_query      = new WP_Query(
				[
					'post_type'   => $post_type_slug,
					'post_status' => 'publish',
				]
			);
			$post_count       = $count_query->found_posts;

			$post_type_content = sprintf(
				/* translators: %d: post count, %s: post name */
				esc_html__( 'There are %1$d %2$s.', 'site-counts' ),
				absint( $post_count ),
				esc_html( $post_type_object->labels->name )
			);
			$post_types_markup = $post_types_markup . '<li>' . $post_type_content . '</li>';
		}

		$query = new WP_Query(
			[
				'posts_per_page' => 6,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'date_query'     => [
					[
						'hour'    => 9,
						'compare' => '>=',
					],
					[
						'hour'    => 17,
						'compare' => '<=',
					],
				],
				'tag'            => 'foo',
				'category_name'  => 'baz',
			]
		);

		if ( $query->have_posts() ) {
			$foo_baz_posts_content = '';

			while ( $query->have_posts() ) {
				$query->the_post();
				if ( $current_post_id && get_the_ID() === $current_post_id ) {
					continue;
				}

				$foo_baz_posts_content = $foo_baz_posts_content . '<li> ' . esc_html( $query->post->post_title ) . ' </li>';
			}

			$foo_baz_heading      = esc_html__( '5 posts with the tag of foo and the category of baz', 'site-counts' );
			$foo_baz_posts_markup = sprintf(
				'<h2>%1$s</h2><ul>%2$s</ul>',
				$foo_baz_heading,
				$foo_baz_posts_content
			);
		}

		$colors          = block_core_page_list_build_css_colors( $block->context );
		$font_sizes      = block_core_page_list_build_css_font_sizes( $block->context );
		$classes         = array_merge( $colors['css_classes'], $font_sizes['css_classes'] );
		$style_attribute = ( $colors['inline_styles'] . $font_sizes['inline_styles'] );
		$css_classes     = trim( implode( ' ', $classes ) );

		$wrapper_attributes = get_block_wrapper_attributes(
			[
				'class' => $css_classes,
				'style' => $style_attribute,
			]
		);

		$current_post_markup = sprintf(
			/* translators: %d: current post ID */
			esc_html__( 'The current post ID is %d', 'site-counts' ),
			absint( $current_post_id )
		);

		$wrapper_markup = apply_filters(
			'sitecounts_block_markup',
			$wrapper_markup,
			$wrapper_attributes,
			$post_types_markup,
			$current_post_markup,
			$foo_baz_posts_markup
		);

		$final_markup = sprintf(
			$wrapper_markup,
			$wrapper_attributes,
			$post_types_markup,
			$current_post_markup,
			$foo_baz_posts_markup
		);

		return apply_filters( 'sitecounts_block_content', $final_markup );
	}
}
