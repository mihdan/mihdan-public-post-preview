<?php
/**
 * Plugin Name: Mihdan: Public Post Preview
 * Description: Публичная ссылка на пост до его публикации
 * Plugin URI:  https://github.com/mihdan/mihdan-public-post-preview/
 * Version:     1.9.12.1
 * Author:      Mikhail Kobzarev
 * Author URI:  https://www.kobzarev.com/
 * Text Domain: mihdan-public-post-preview
 * GitHub Plugin URI: https://github.com/mihdan/mihdan-public-post-preview/
 * Contributors: mihdan, tkama
 */

/**
 * @package mihdan-public-post-preview
 * @link    https://poltavcev.biz/2018/10/31/kak-pokazat-ne-opublikovannuyu-zapis-na-wordpress/?tg_rhash=731cd3d47acb43%D0%9A%D0%B0%D0%BA
 * @link    https://wordpress.stackexchange.com/questions/218168/how-to-make-draft-posts-or-posts-in-review-accessible-via-full-url-slug
 * @link    https://wordpress.stackexchange.com/questions/41588/how-to-get-the-clean-permalink-in-a-draft
 */

namespace Mihdan\PublicPostPreview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MIHDAN_PUBLIC_POST_PREVIEW_FILE', __FILE__ );
define( 'MIHDAN_PUBLIC_POST_PREVIEW_DIR', __DIR__ );
define( 'MIHDAN_PUBLIC_POST_PREVIEW_VERSION', '1.9.12.1' );

if ( file_exists( __DIR__ . '/src/Core.php' ) ) {
	require_once __DIR__ . '/src/Core.php';

	/**
	 * Инициализации плагина.
	 */
	add_action( 'after_setup_theme', [ 'Mihdan\PublicPostPreview\Core', 'get_instance' ] );
}
