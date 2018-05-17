<?php
	$avatar_url = get_avatar_url( $author['id'] );
	$author = $this->get( 'author' );
?>

<div class="liveblog-meta-author">
	<div class="liveblog-meta-author-avatar">
		<amp-img alt="A view of the sea"
		src="<?php echo esc_html( $avatar_url ); ?>"
		width="20"
		height="20"
		layout="responsive">
		</amp-img>
	</div>
	<span class="liveblog-meta-author-name"><?php echo esc_html( $author['name'] ); ?></span>
</div>
