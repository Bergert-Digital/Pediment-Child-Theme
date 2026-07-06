<?php
/**
 * Pediment demo showcase content (ported from the former parent seeder).
 * `wp pediment-child seed-demo` — what a fresh template copy seeds by default.
 *
 * @package PedimentChild
 */

namespace PedimentChild\Seed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Demo showcase seeder: builds the home/about/contact/blog/mega-demo pages,
 * sideloads the demo image + logo, and creates sample posts. Idempotent.
 */
class Demo {

	/**
	 * Register the `wp pediment-child seed-demo` CLI command.
	 */
	public static function register_cli(): void {
		\WP_CLI::add_command( 'pediment-child seed-demo', array( __CLASS__, 'cli_seed_demo' ) );
	}

	/**
	 * CLI entry point: seed the demo showcase and report the log.
	 */
	public static function cli_seed_demo(): void {
		$r = self::seed_demo();
		foreach ( $r['log'] as $l ) {
			\WP_CLI::log( $l );
		}
		\WP_CLI::success( $r['summary'] );
	}

	/**
	 * Seed the Pediment demo showcase pages + sample posts. Idempotent.
	 *
	 * @return array{ok:bool,summary:string,log:string[]}
	 */
	public static function seed_demo(): array {
		self::seed_demo_image();
		self::seed_demo_logo();

		$pages = array(
			'home'      => array(
				'title'   => 'Home',
				'content' => self::pediment_landing_content(),
				'front'   => true,
			),
			'about'     => array(
				'title'   => 'About',
				'content' => self::page_band_hero( 'ABOUT', 'About us', 'Who we are and what we do.' ) .
					self::page_band_content(
						'<!-- wp:pediment/prose -->' .
							'<!-- wp:paragraph --><p>Tell your story here. Keep it human and specific.</p><!-- /wp:paragraph -->' .
						'<!-- /wp:pediment/prose -->'
					),
				'front'   => false,
			),
			'contact'   => array(
				'title'   => 'Contact',
				'content' => self::page_band_hero( 'CONTACT', 'Contact', 'Tell us about your project.' ) .
					self::page_band_content( '<!-- wp:pediment/contact-form {"includePhone":true} /-->' ),
				'front'   => false,
			),
			'blog'      => array(
				'title'   => 'Blog',
				// home.html renders the listing; the page's own content is unused.
				'content' => '',
				'front'   => false,
			),
			'mega-demo' => array(
				'title'   => 'Mega Menu Demo',
				'content' => self::mega_demo_content(),
				'front'   => false,
			),
		);

		list( $ids, $log ) = Seed::upsert_pages_from( $pages );

		if ( ! empty( $ids['blog'] ) ) {
			update_option( 'page_for_posts', $ids['blog'] );
		}

		self::seed_sample_posts();

		return array(
			'ok'      => true,
			'summary' => 'Pediment demo seeded.',
			'log'     => $log,
		);
	}

	/**
	 * The Pediment landing pattern content for the Home page.
	 *
	 * Reads the registered `pediment/pediment-landing` pattern. Falls back to a
	 * minimal valid block composition so seeding never writes an empty Home even
	 * if patterns are unavailable.
	 *
	 * @return string Block markup.
	 */
	private static function pediment_landing_content(): string {
		$content = '';
		if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
			$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/pediment-landing' );
			if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
				$content = (string) $pattern['content'];
			}
		}
		if ( '' === $content ) {
			$content = '<!-- wp:pediment/hero {"variant":"centered","headline":"Welcome","subheadline":"A short benefit-led promise.","ctaText":"Get started","ctaUrl":"/contact","align":"wide"} /-->' .
				'<!-- wp:pediment/cta {"title":"Ready to start?","body":"Tell us about your project.","primaryText":"Contact us","primaryUrl":"/contact","align":"wide"} /-->' .
				'<!-- wp:pediment/blog-index {"count":3,"align":"wide"} /-->';
		}
		return self::apply_demo_image( $content );
	}

	/**
	 * Mega-menu demo fixture content for the /mega-demo/ page.
	 *
	 * Reuses the registered `pediment/mega-menu-header` pattern so the e2e suite
	 * always asserts against the canonical fixture. Falls back to a minimal
	 * inline composition (kept in sync with the pattern) so seeding never writes
	 * an empty page if pattern registration is unavailable at seed time.
	 *
	 * @return string Block markup.
	 */
	private static function mega_demo_content(): string {
		if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
			$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/mega-menu-header' );
			if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
				return (string) $pattern['content'];
			}
		}
		return '<!-- wp:group {"className":"mega-demo","layout":{"type":"constrained"}} -->' .
			'<div class="wp-block-group mega-demo">' .
				'<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal"}} -->' .
					'<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Banking","links":[{"label":"Checking","url":"#checking"}]}]} /-->' .
				'<!-- /wp:navigation -->' .
			'</div>' .
			'<!-- /wp:group -->';
	}

	/**
	 * Idempotently sideload the demo image and tag it for easy cleanup.
	 *
	 * The marker meta `_pediment_child_seed_demo` makes removal trivial:
	 *   wp post list --post_type=attachment --meta_key=_pediment_child_seed_demo --field=ID
	 *   | xargs -I{} wp post delete {} --force
	 *
	 * @return int Attachment ID, or 0 on failure.
	 */
	private static function seed_demo_image(): int {
		// phpcs:disable WordPress.DB.SlowDBQuery -- seed lookup runs once per activation; meta lookup acceptable here.
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_pediment_child_seed_demo',
				'meta_value'  => '1',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$src = get_theme_file_path( 'assets/seed/dylan-gillis-KdeqA3aTnBY-unsplash.jpg' );
		if ( ! file_exists( $src ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return 0;
		}
		$filename = wp_unique_filename( $uploads['path'], basename( $src ) );
		$dest     = trailingslashit( $uploads['path'] ) . $filename;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- copy() can emit non-fatal warnings; return value drives the failure path.
		if ( ! @copy( $src, $dest ) ) {
			return 0;
		}

		$filetype  = wp_check_filetype( $dest, null );
		$attach_id = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/jpeg',
				'post_title'     => 'Demo image (Dylan Gillis on Unsplash)',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$dest
		);
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			wp_delete_file( $dest );
			return 0;
		}

		wp_update_attachment_metadata( (int) $attach_id, wp_generate_attachment_metadata( (int) $attach_id, $dest ) );
		update_post_meta( (int) $attach_id, '_pediment_child_seed_demo', '1' );

		return (int) $attach_id;
	}

	/**
	 * Idempotently sideload the wide demo logo and set it as the site's
	 * Custom Logo. Mirrors self::seed_demo_image().
	 *
	 * The marker meta `_pediment_child_seed_demo_logo` makes removal trivial:
	 *   wp post list --post_type=attachment --meta_key=_pediment_child_seed_demo_logo --field=ID
	 *   | xargs -I{} wp post delete {} --force
	 *
	 * @return int Attachment ID, or 0 on failure.
	 */
	private static function seed_demo_logo(): int {
		// phpcs:disable WordPress.DB.SlowDBQuery -- seed lookup runs once per activation; meta lookup acceptable here.
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_pediment_child_seed_demo_logo',
				'meta_value'  => '1',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery
		if ( ! empty( $existing ) ) {
			$id = (int) $existing[0];
			if ( (int) get_theme_mod( 'custom_logo', 0 ) !== $id ) {
				set_theme_mod( 'custom_logo', $id );
			}
			return $id;
		}

		$src = get_theme_file_path( 'assets/seed/logo-demo.svg' );
		if ( ! file_exists( $src ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return 0;
		}
		$filename = wp_unique_filename( $uploads['path'], basename( $src ) );
		$dest     = trailingslashit( $uploads['path'] ) . $filename;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- copy() can emit non-fatal warnings; return value drives the failure path.
		if ( ! @copy( $src, $dest ) ) {
			return 0;
		}

		$attach_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/svg+xml',
				'post_title'     => 'Demo logo (Pediment)',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$dest
		);
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			wp_delete_file( $dest );
			return 0;
		}

		update_post_meta( (int) $attach_id, '_pediment_child_seed_demo_logo', '1' );
		set_theme_mod( 'custom_logo', (int) $attach_id );

		return (int) $attach_id;
	}

	/**
	 * Bake the seeded demo attachment into the pediment-landing pattern content:
	 * adds `mediaId` to the stat-card hero and fills the empty approach-band image.
	 *
	 * @param string $content Raw pattern markup.
	 * @return string Pattern markup with the demo image baked in.
	 */
	private static function apply_demo_image( string $content ): string {
		$id = self::seed_demo_image();
		if ( ! $id ) {
			return $content;
		}

		$url = (string) wp_get_attachment_image_url( $id, 'large' );

		$walk = function ( array &$block ) use ( &$walk, $id, $url ) {
			$name = $block['blockName'] ?? '';

			if (
				'pediment/hero' === $name
				&& 'stat-card' === ( $block['attrs']['variant'] ?? '' )
				&& empty( $block['attrs']['mediaId'] )
			) {
				$block['attrs']['mediaId'] = $id;
			}

			if (
				'core/image' === $name
				&& in_array(
					'starter-approach__image',
					preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ),
					true
				)
				&& empty( $block['attrs']['id'] )
			) {
				$block['attrs']['id']  = $id;
				$figure                = sprintf(
					'<figure class="wp-block-image size-large starter-approach__image"><img src="%s" alt="" class="wp-image-%d" /></figure>',
					esc_url( $url ),
					$id
				);
				$block['innerHTML']    = $figure;
				$block['innerContent'] = array( $figure );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as &$inner ) {
					$walk( $inner );
				}
				unset( $inner );
			}
		};

		$blocks = parse_blocks( $content );
		foreach ( $blocks as &$block ) {
			$walk( $block );
		}
		unset( $block );

		return serialize_blocks( $blocks );
	}

	/**
	 * Idempotently create sample categories + posts so the Insights band
	 * (pediment/blog-index) renders fully. Skips anything that already exists.
	 *
	 * @return void
	 */
	private static function seed_sample_posts(): void {
		$categories = array(
			'insights'  => 'Insights',
			'briefings' => 'Briefings',
			'notes'     => 'Notes',
		);
		$cat_ids    = array();
		foreach ( $categories as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term ) {
				$cat_ids[ $slug ] = (int) $term->term_id;
				continue;
			}
			$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $created ) ) {
				$cat_ids[ $slug ] = (int) $created['term_id'];
			}
		}

		$posts         = array(
			array(
				'slug'  => 'sample-insight-one',
				'title' => 'A practical insight on getting started',
				'cat'   => 'insights',
			),
			array(
				'slug'  => 'sample-insight-two',
				'title' => 'What good looks like, in plain terms',
				'cat'   => 'insights',
			),
			array(
				'slug'  => 'sample-briefing-one',
				'title' => 'A short briefing on a common decision',
				'cat'   => 'briefings',
			),
			array(
				'slug'  => 'sample-briefing-two',
				'title' => 'Trade-offs worth weighing early',
				'cat'   => 'briefings',
			),
			array(
				'slug'  => 'sample-note-one',
				'title' => 'A quick note on process',
				'cat'   => 'notes',
			),
			array(
				'slug'  => 'sample-note-two',
				'title' => 'A quick note on outcomes',
				'cat'   => 'notes',
			),
		);
		$demo_image_id = self::seed_demo_image();

		foreach ( $posts as $p ) {
			$existing = get_page_by_path( $p['slug'], OBJECT, 'post' );
			if ( $existing ) {
				if ( $demo_image_id && ! has_post_thumbnail( $existing ) ) {
					set_post_thumbnail( $existing, $demo_image_id );
				}
				continue;
			}
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => $p['title'],
					'post_name'    => $p['slug'],
					'post_excerpt' => 'A one-sentence summary of this sample article, ready to be replaced.',
					'post_content' => '<!-- wp:paragraph --><p>Replace this sample article with your own writing.</p><!-- /wp:paragraph -->',
				),
				true
			);
			if ( ! is_wp_error( $post_id ) && isset( $cat_ids[ $p['cat'] ] ) ) {
				wp_set_post_categories( (int) $post_id, array( $cat_ids[ $p['cat'] ] ) );
			}
			if ( ! is_wp_error( $post_id ) && $demo_image_id ) {
				set_post_thumbnail( (int) $post_id, $demo_image_id );
			}
		}
	}

	/**
	 * Build the surface-band hero used on simple pages (About, Contact, etc.).
	 *
	 * Mirrors the blog index hero (templates/home.html): a full-bleed
	 * .starter-band.is-style-band-surface with a centered kicker eyebrow, H1, and
	 * lead paragraph. The H1 here serves as the page heading (page.html drops
	 * wp:post-title, so content owns the H1).
	 *
	 * @param string $kicker   Short uppercase eyebrow label (e.g. "ABOUT").
	 * @param string $headline H1 text.
	 * @param string $lead     Subtitle paragraph text.
	 * @return string Block markup.
	 */
	private static function page_band_hero( string $kicker, string $headline, string $lead ): string {
		return '<!-- wp:group {"align":"full","className":"starter-band is-style-band-surface","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->' .
			'<div class="wp-block-group alignfull starter-band is-style-band-surface is-layout-constrained wp-block-group-is-layout-constrained" style="margin-top:0;margin-bottom:0">' .
				'<!-- wp:paragraph {"align":"center","className":"kicker"} --><p class="has-text-align-center kicker">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' .
				'<!-- wp:heading {"textAlign":"center","level":1} --><h1 class="wp-block-heading has-text-align-center">' . esc_html( $headline ) . '</h1><!-- /wp:heading -->' .
				'<!-- wp:paragraph {"align":"center","className":"lead"} --><p class="has-text-align-center lead">' . esc_html( $lead ) . '</p><!-- /wp:paragraph -->' .
			'</div>' .
			'<!-- /wp:group -->';
	}

	/**
	 * Wrap page content (prose, contact form, etc.) in a transparent .starter-band
	 * so it inherits the same vertical rhythm as the hero band above it.
	 * Theme.css zeroes block-gap between site-block children, so without a band
	 * the content butts directly against the hero.
	 *
	 * @param string $inner_blocks Already-serialized block markup to wrap.
	 * @return string
	 */
	private static function page_band_content( string $inner_blocks ): string {
		return '<!-- wp:group {"align":"full","className":"starter-band","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->' .
			'<div class="wp-block-group alignfull starter-band is-layout-constrained wp-block-group-is-layout-constrained" style="margin-top:0;margin-bottom:0">' .
				$inner_blocks .
			'</div>' .
			'<!-- /wp:group -->';
	}
}
