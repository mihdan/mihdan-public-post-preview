<?php
/**
 * Plugin Name: Mihdan: Public Post Preview
 * Description: Публичная ссылка на пост до его публикации
 * Plugin URI:  https://github.com/mihdan/mihdan-public-post-preview/
 * Version:     1.9.5
 * Author:      Mikhail Kobzarev
 * Author URI:  https://www.kobzarev.com/
 * Text Domain: mihdan-public-post-preview
 * GitHub Plugin URI: https://github.com/mihdan/mihdan-public-post-preview/
 * Contributors: mihdan, tkama
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
	const VERSION     = '1.9.5';

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
		$this->includes();
		$this->setup();
		$this->init();
	}

	public function includes() {
		if ( file_exists( WP_PLUGIN_DIR . '/wp-php-console/vendor/autoload.php' ) ) {

			require_once WP_PLUGIN_DIR . '/wp-php-console/vendor/autoload.php';

			if ( ! class_exists( 'PC', false ) ) {
				\PhpConsole\Helper::register();
			}
		}
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
		add_action( 'wp_insert_post', array( $this, 'fix_post_name' ), 999, 3 );
		add_filter( 'posts_results', array( $this, 'posts_results' ), 10, 2 );
		add_filter( 'preview_post_link', array( $this, 'preview_post_link' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'draft_preview_post_states_mark' ), 10, 2 );
	}

	/**
	 * Обновляем поле post_name у записи при изменении статуса
	 *
	 * @param          $post_ID
	 * @param \WP_Post $post
	 * @param          $update
	 */
	public function fix_post_name( $post_ID, \WP_Post $post, $update ) {
		global $wpdb;

		if ( $this->is_post_previewable( $post ) ) {
			$wpdb->update( $wpdb->posts, array( 'post_name' => sanitize_title( $post->post_title ) ), array( 'ID' => $post_ID ) );
			clean_post_cache( $post_ID );
		}
	}

	/**
	 * Добавляем метку к посту в списке записей, что для него активно превью
	 *
	 * @param array    $states массив статусов.
	 * @param \WP_Post $post объект поста.
	 *
	 * @return array
	 */
	public function draft_preview_post_states_mark( $states, \WP_Post $post ) {
		if ( $this->is_post_previewable( $post ) ) {
			$states[] = 'Публичное превью';
		}

		return $states;
	}

	/**
	 * Генерим красивую ссылку у записи в списке постов в админке.
	 *
	 * @param string   $preview_link дефолтная ссылка.
	 * @param \WP_Post $post
	 *
	 * @return string
	 */
	public function preview_post_link( $preview_link, \WP_Post $post ) {
		if ( $this->is_post_previewable( $post ) ) {
			return $this->get_permalink( $post->ID );
		}

		return $preview_link;
	}

	/**
	 * Удалить галочку из базы при публикации поста
	 *
	 * @param string   $new_status старый статус
	 * @param string   $old_status новый статус
	 * @param \WP_Post $post объект поста
	 */
	public function remove_preview( $new_status, $old_status, \WP_Post $post ) {
		if ( 'publish' === $new_status && 'publish' !== $old_status && in_array( $post->post_status, $this->post_status, true ) ) {
			delete_post_meta( $post->ID, self::META_NAME );
		}
	}

	/**
	 * Превьюбельный ли пост ))))
	 *
	 * @param \WP_Post $post объект поста
	 *
	 * @return boolean
	 */
	public function is_post_previewable( \WP_Post $post ) {
		// Значение по умолчанию.
		$result = false;

		// Получаем мету из базы.
		$result = get_post_meta( $post->ID, self::META_NAME, true );

		// Фильтруем
		$result = apply_filters( 'mihdan_public_post_preview_is_post_previewable', $result, $post );

		return $result;
	}

	/**
	 * Получить красивую ссылку на пост
	 *
	 * @param int $post_id идентификатор записи
	 *
	 * @return string
	 */
	public function get_permalink( $post_id ) {

		if ( ! function_exists( 'get_sample_permalink' ) ) {
			require_once ABSPATH . '/wp-admin/includes/post.php';
		}

		list( $permalink, $postname ) = get_sample_permalink( $post_id );

		return str_replace( array( '%pagename%', '%postname%' ), $postname, $permalink );
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

		if ( ! $wp_query->is_admin && $wp_query->is_main_query() && $wp_query->is_single() && 1 === count( $posts ) ) {

			/** @var \WP_Post $post */
			$post = & $posts[0];

			if ( in_array( $post->post_type, $this->post_type, true ) && in_array( $post->post_status, $this->post_status, true ) && $this->is_post_previewable( $post ) ) {
				// Запомним статус
				$old_status = $post->post_status;

				// Чтобы работало is_preview
				$wp_query->is_preview = true;

				// Чтобы пройти проверки
				$post->post_status = 'publish';

				add_action( 'wp', function() use ( $post, $old_status ) {
					$post->post_status = $old_status;
				} );
			}
		}

		return $posts;
	}

	/**
	 * Подклюаем стили и скрипты в админку
	 */
	public function enqueue_script() {
		wp_enqueue_script( self::PLUGIN_NAME, plugins_url( 'admin/assets/js/app.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
		wp_enqueue_style( self::PLUGIN_NAME, plugins_url( 'admin/assets/css/app.css', __FILE__ ), array(), self::VERSION );
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

		// Классы для блока со ссылкой.
		$class = '';

		// Включен ли предпросмотр для поста.
		$is_previewable = $this->is_post_previewable( $post );

		if ( ! $is_previewable ) {
			$class = 'hidden';
		}
		?>
		<div class="misc-pub-section">
			<label title="Включить/выключить публичную сылку"><input type="checkbox" data-post-id="<?php echo absint( $post->ID ); ?>" id="<?php echo esc_attr( self::PLUGIN_NAME ); ?>_toggler" <?php checked( '1', $is_previewable ); ?> /> <span>Публичная ссылка</span></label>
			<input type="text" id="<?php echo esc_attr( self::PLUGIN_NAME ); ?>_link" class="<?php echo esc_attr( $class ); ?>" value="<?php echo esc_url( $this->get_permalink( $post->id ) ); ?>" >
		</div>
		<?php
	}

	/**
	 * Включаем/Выключаем превью для записи
	 */
	public function mppp_toggle() {
		$value   = ( 'true' === $_REQUEST['value'] ) ? 1 : 0;
		$post_id = absint( $_REQUEST['post_id'] );
		$post    = get_post( $post_id );

		// Обновляем мету с галочкой
		if ( 1 === $value ) {
			// Важно для работы запроса предпросмотра задать post_name
			$args = array(
				'ID'         => $post->ID,
				'post_title' => $post->post_title,
				'post_name'  => sanitize_title( $post->post_title ),
				'meta_input' => [ self::META_NAME => $value ],
			);

			// Обновим пост
			wp_update_post( wp_slash( $args ) );

		} else {
			// Удалим post_name у записи. Важно!!!
			$args = array(
				'ID'        => $post->ID,
				'post_name' => '',
			);

			wp_update_post( $args );

			// Удалим мету
			delete_post_meta( $post_id, self::META_NAME );
		}

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
