<?php
/** @var array $attributes */

$headline  = isset( $attributes['headline'] ) ? (string) $attributes['headline'] : '';
$body      = isset( $attributes['body'] )     ? (string) $attributes['body']     : '';
$link_text = isset( $attributes['linkText'] ) ? (string) $attributes['linkText'] : '';
$link_url  = isset( $attributes['linkUrl'] )  ? (string) $attributes['linkUrl']  : '';

if ( '' === $headline && '' === $body ) {
	return '';
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-child-promo-banner' ) );

ob_start();
?>
<aside <?php echo $wrapper; // phpcs:ignore ?>>
	<?php if ( '' !== $headline ) : ?>
		<strong class="starter-child-promo-banner__headline"><?php echo wp_kses_post( $headline ); ?></strong>
	<?php endif; ?>
	<?php if ( '' !== $body ) : ?>
		<p class="starter-child-promo-banner__body"><?php echo wp_kses_post( $body ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $link_text && '' !== $link_url ) : ?>
		<a class="starter-child-promo-banner__link" href="<?php echo esc_url( $link_url ); ?>"><?php echo wp_kses_post( $link_text ); ?></a>
	<?php endif; ?>
</aside>
<?php
echo ob_get_clean();
