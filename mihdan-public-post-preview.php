<?php
/**
 * Plugin Name: Mihdan: Public Post Preview
 * Description: Публичная ссылка на пост до его публикации
 * Plugin URI:  https://github.com/mihdan/mihdan-public-post-preview/
 * Version:     1.5
 * Author:      Mikhail Kobzarev
 * Author URI:  https://www.kobzarev.com/
 * Text Domain: mihdan-public-post-preview
 * GitHub Plugin URI: https://github.com/mihdan/mihdan-public-post-preview/
 */

/**
 * @link https://poltavcev.biz/2018/10/31/kak-pokazat-ne-opublikovannuyu-zapis-na-wordpress/?tg_rhash=731cd3d47acb43%D0%9A%D0%B0%D0%BA
 * @link https://wordpress.stackexchange.com/questions/218168/how-to-make-draft-posts-or-posts-in-review-accessible-via-full-url-slug
 * @link https://wordpress.stackexchange.com/questions/41588/how-to-get-the-clean-permalink-in-a-draft
 */

namespace Mihdan_Public_Post_Preview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Core {

	const PLUGIN_NAME = 'mppp';
	const META_NAME   = 'mppp';
	const VERSION     = '1.5';

	/**
	 * Instance
	 *
	 * @since 1.0
	 *
	 * @access private
	 * @static
	 *
	 * @var Core The single instance of the class.
	 */
	private static $_instance = null;

	/**
	 * @var array $post_type массив типов поста
	 */
	private $post_type;

	/**
	 * @var array $post_status массив статусов поста
	 */
	private $post_status;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.0
	 *
	 * @access public
	 * @static
	 *
	 * @return Core An instance of the class.
	 */
	public static function get_instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;

	}

	/**
	 * Constructor
	 *
	 * @since 1.0
	 *
	 * @access public
	 */
	public function __construct() {
		$this->setup();
		$this->init();
	}

	public function setup() {
		$this->post_status = apply_filters( 'mihdan_public_post_preview_post_status', array( 'draft' ) );
		$this->post_type   = apply_filters( 'mihdan_public_post_preview_post_type', array( 'post' ) );
	}

	/**
	 * Инициализация
	 */
	public function init() {
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_action( 'wp_ajax_mppp_toggle', array( $this, 'mppp_toggle' ) );
		add_action( 'transition_post_status', array( $this, 'remove_preview' ), 10, 3 );
		add_filter( 'posts_results', array( $this, 'posts_results' ), 10, 2 );
	}

	/**
	 * Удалить галочку из базы при публикации поста
	 *
	 * @param string   $new_status старый статус
	 * @param string   $old_status новый статус
	 * @param \WP_Post $post объект поста
	 */
	public function remove_preview( $new_status, $old_status, \WP_Post $post ) {
		if ( 'publish' === $new_status ) {
			delete_post_meta( $post->ID, self::META_NAME );
		}
	}

	/**
	 * Получить красивую ссылку на пост
	 *
	 * @param int $post_id идентификатор записи
	 *
	 * @return string
	 */
	public function get_permalink( $post_id ) {

		// Получим статус переданного поста.
		$post_status = get_post_status( $post_id );

		// Коцаем только ссылки в черновиках.
		if ( in_array( $post_status, $this->post_status, true ) ) {

			require_once ABSPATH . '/wp-admin/includes/post.php';
			list( $permalink, $postname ) = get_sample_permalink( $post_id );

			return str_replace( '%postname%', $postname, $permalink );
		} else {
			return get_permalink( $post_id );
		}
	}

	/**
	 * Создаем фейковое свойство для `$wp_query->_draft_posts`
	 * и передаем туда пост-черновик, у которого
	 * в базе проставлена соответствующая галочка
	 *
	 * @param array     $posts массив записей
	 * @param \WP_Query $wp_query
	 *
	 * @return mixed
	 */
	public function posts_results( $posts, \WP_Query $wp_query ) {

		// Работаем, если это тип поста - запись
		if ( ! $wp_query->is_admin && $wp_query->is_single() ) {

			$post = $posts[0];

			if ( in_array( $post->post_type, $this->post_type, true ) && in_array( $post->post_status, $this->post_status, true ) ) {

				// Включен ли предпросмотр для поста.
				$is_preview_enabled = (int) get_post_meta( $post->ID, self::META_NAME, true );

				if ( 1 === $is_preview_enabled ) {
					$wp_query->_draft_posts = $posts;

					add_filter( 'the_posts', array( $this, 'show_draft_post' ), 10, 2 );
				}
			}
		}

		return $posts;
	}

	/**
	 * Вместо стандартного свойства `$wp_query->posts`
	 * подсовываем наше фейковое `$wp_query->_draft_posts`
	 *
	 * @param array     $posts
	 * @param \WP_Query $wp_query
	 *
	 * @return mixed
	 */
	public function show_draft_post( $posts, \WP_Query $wp_query ) {
		remove_filter( 'the_posts', array( $this, 'show_draft_post' ), 10 );

		return $wp_query->_draft_posts;
	}

	/**
	 * Подклюаем стили и скрипты в админку
	 */
	public function enqueue_script() {
		wp_enqueue_script( self::PLUGIN_NAME, plugins_url( 'assets/js/app.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
		wp_enqueue_style( self::PLUGIN_NAME, plugins_url( 'assets/css/app.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Добавляем чекбокс в метабокс с выбором статуса поста
	 */
	public function add_metabox() {
		global $post;

		// Рисуем метабокс только для черновика
		if ( ! in_array( $post->post_status, $this->post_status, true ) ) {
			return;
		}

		// Включен ли предпросмотр для поста.
		$is_preview_enabled = (int) get_post_meta( $post->ID, self::META_NAME, true );

		// Классы для блока со ссылкой.
		$class = '';

		if ( 1 !== $is_preview_enabled ) {
			$class = 'hidden';
		}
		?>
		<div class="misc-pub-section">
			<label title="Включить/выключить публичную сылку"><input type="checkbox" data-post-id="<?php echo absint( $post->ID ); ?>" id="<?php echo esc_attr( self::PLUGIN_NAME ); ?>_toggler" <?php checked( '1', get_post_meta( $post->ID, self::META_NAME, true ) ); ?> /> <span>Публичная ссылка</span></label>
			<div id="<?php echo esc_attr( self::PLUGIN_NAME ); ?>_link" class="<?php echo esc_attr( $class ); ?>">
				<?php echo esc_url( $this->get_permalink( $post->id ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Включаем/Выключаем превью для записи
	 */
	public function mppp_toggle() {
		$value   = ( 'true' === $_REQUEST['value'] ) ? 1 : 0;
		$post_id = absint( $_REQUEST['post_id'] );

		// Обновляем мету с галочкой
		if ( 1 === $value ) {
			update_post_meta( $post_id, self::META_NAME, $value );
		} else {
			delete_post_meta( $post_id, self::META_NAME );
		}

		// Очистить кеш поста
		clean_post_cache( $post_id );

		// Формируем ответ для JS
		$result = array(
			'value' => $value,
			'link'  => $this->get_permalink( $post_id ),
		);

		wp_send_json_success( $result );
	}
}

/**
 * Хелпре для инициализации плагина
 *
 * @return Core
 */
function mihdan_public_post_preview() {
	return Core::get_instance();
}

add_action( 'after_setup_theme', 'Mihdan_Public_Post_Preview\mihdan_public_post_preview' );

// eof;
