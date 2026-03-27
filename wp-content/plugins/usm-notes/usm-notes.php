<?php
/**
 * Plugin Name: USM Notes
 * Description: Учебный плагин для лабораторной работы: CPT «Заметки», таксономия приоритетов, Due Date и вывод на фронтенде.
 * Version: 1.0.0
 * Author: Student USM
 * Text Domain: usm-notes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'USM_NOTES_DUE_DATE_META_KEY' ) ) {
	define( 'USM_NOTES_DUE_DATE_META_KEY', '_usm_notes_due_date' );
}

if ( ! defined( 'USM_NOTES_ERROR_TRANSIENT_KEY' ) ) {
	define( 'USM_NOTES_ERROR_TRANSIENT_KEY', 'usm_notes_due_date_error_' );
}

/**
 * Регистрирует CPT "Заметки".
 */
function usm_notes_register_post_type() {
	$labels = array(
		'name'               => __( 'Notes', 'usm-notes' ),
		'singular_name'      => __( 'Note', 'usm-notes' ),
		'menu_name'          => __( 'Notes', 'usm-notes' ),
		'name_admin_bar'     => __( 'Note', 'usm-notes' ),
		'add_new'            => __( 'Add New', 'usm-notes' ),
		'add_new_item'       => __( 'Add New Note', 'usm-notes' ),
		'new_item'           => __( 'New Note', 'usm-notes' ),
		'edit_item'          => __( 'Edit Note', 'usm-notes' ),
		'view_item'          => __( 'View Note', 'usm-notes' ),
		'all_items'          => __( 'All Notes', 'usm-notes' ),
		'search_items'       => __( 'Search Notes', 'usm-notes' ),
		'not_found'          => __( 'No notes found.', 'usm-notes' ),
		'not_found_in_trash' => __( 'No notes found in Trash.', 'usm-notes' ),
	);

	$args = array(
		'labels'       => $labels,
		'public'       => true,
		'has_archive'  => true,
		'menu_icon'    => 'dashicons-welcome-write-blog',
		'show_in_rest' => true,
		'rewrite'      => array( 'slug' => 'notes' ),
		'supports'     => array( 'title', 'editor', 'author', 'thumbnail' ),
	);

	register_post_type( 'usm_note', $args );
}
add_action( 'init', 'usm_notes_register_post_type' );

/**
 * Регистрирует таксономию приоритета.
 */
function usm_notes_register_taxonomy() {
	$labels = array(
		'name'              => __( 'Priorities', 'usm-notes' ),
		'singular_name'     => __( 'Priority', 'usm-notes' ),
		'search_items'      => __( 'Search Priorities', 'usm-notes' ),
		'all_items'         => __( 'All Priorities', 'usm-notes' ),
		'parent_item'       => __( 'Parent Priority', 'usm-notes' ),
		'parent_item_colon' => __( 'Parent Priority:', 'usm-notes' ),
		'edit_item'         => __( 'Edit Priority', 'usm-notes' ),
		'update_item'       => __( 'Update Priority', 'usm-notes' ),
		'add_new_item'      => __( 'Add New Priority', 'usm-notes' ),
		'new_item_name'     => __( 'New Priority Name', 'usm-notes' ),
		'menu_name'         => __( 'Priority', 'usm-notes' ),
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'priority' ),
	);

	register_taxonomy( 'usm_priority', array( 'usm_note' ), $args );
}
add_action( 'init', 'usm_notes_register_taxonomy' );

/**
 * Создает базовые термины приоритета (High/Medium/Low).
 */
function usm_notes_ensure_default_priorities() {
	$defaults = array(
		'high'   => __( 'High', 'usm-notes' ),
		'medium' => __( 'Medium', 'usm-notes' ),
		'low'    => __( 'Low', 'usm-notes' ),
	);

	foreach ( $defaults as $slug => $name ) {
		if ( ! term_exists( $slug, 'usm_priority' ) ) {
			wp_insert_term(
				$name,
				'usm_priority',
				array(
					'slug' => $slug,
				)
			);
		}
	}
}
add_action( 'init', 'usm_notes_ensure_default_priorities', 20 );

/**
 * Активация плагина.
 */
function usm_notes_activate() {
	usm_notes_register_post_type();
	usm_notes_register_taxonomy();
	usm_notes_ensure_default_priorities();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'usm_notes_activate' );

/**
 * Деактивация плагина.
 */
function usm_notes_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'usm_notes_deactivate' );

/**
 * Регистрирует стили для вывода заметок.
 */
function usm_notes_register_assets() {
	wp_register_style(
		'usm-notes-frontend',
		plugins_url( 'assets/css/usm-notes.css', __FILE__ ),
		array(),
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'usm_notes_register_assets' );

/**
 * Добавляет метабокс даты.
 */
function usm_notes_add_due_date_meta_box() {
	add_meta_box(
		'usm_notes_due_date',
		__( 'Due Date', 'usm-notes' ),
		'usm_notes_due_date_meta_box_callback',
		'usm_note',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'usm_notes_add_due_date_meta_box' );

/**
 * Отрисовывает метабокс даты.
 *
 * @param WP_Post $post Текущая запись.
 */
function usm_notes_due_date_meta_box_callback( $post ) {
	$stored_date = get_post_meta( $post->ID, USM_NOTES_DUE_DATE_META_KEY, true );

	wp_nonce_field( 'usm_notes_save_due_date', 'usm_notes_due_date_nonce' );
	?>
	<p>
		<label for="usm_notes_due_date"><strong><?php esc_html_e( 'Reminder Date', 'usm-notes' ); ?></strong></label>
	</p>
	<input
		type="date"
		id="usm_notes_due_date"
		name="usm_notes_due_date"
		value="<?php echo esc_attr( $stored_date ); ?>"
		min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"
		required
	/>
	<p class="description">
		<?php esc_html_e( 'Date is required and cannot be in the past.', 'usm-notes' ); ?>
	</p>
	<?php
}

/**
 * Проверяет корректность даты формата Y-m-d.
 *
 * @param string $date Строка даты.
 * @return bool
 */
function usm_notes_is_valid_date( $date ) {
	$parsed = DateTime::createFromFormat( 'Y-m-d', $date );

	return $parsed && $parsed->format( 'Y-m-d' ) === $date;
}

/**
 * Сохраняет дату напоминания для заметки.
 *
 * @param int     $post_id ID записи.
 * @param WP_Post $post    Объект записи.
 */
function usm_notes_save_due_date( $post_id, $post ) {
	if ( ! isset( $_POST['usm_notes_due_date_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['usm_notes_due_date_nonce'] ) ), 'usm_notes_save_due_date' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( 'usm_note' !== $post->post_type ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$due_date = isset( $_POST['usm_notes_due_date'] )
		? sanitize_text_field( wp_unslash( $_POST['usm_notes_due_date'] ) )
		: '';

	$error_message = '';
	$today         = wp_date( 'Y-m-d' );

	if ( '' === $due_date ) {
		$error_message = __( 'Ошибка: поле Due Date обязательно для заполнения.', 'usm-notes' );
	} elseif ( ! usm_notes_is_valid_date( $due_date ) ) {
		$error_message = __( 'Ошибка: некорректный формат даты. Используйте YYYY-MM-DD.', 'usm-notes' );
	} elseif ( $due_date < $today ) {
		$error_message = __( 'Ошибка: дата напоминания не может быть в прошлом.', 'usm-notes' );
	}

	if ( '' !== $error_message ) {
		usm_notes_force_note_to_draft( $post_id );
		set_transient( USM_NOTES_ERROR_TRANSIENT_KEY . get_current_user_id(), $error_message, MINUTE_IN_SECONDS );
		add_filter( 'redirect_post_location', 'usm_notes_add_error_query_arg' );

		return;
	}

	update_post_meta( $post_id, USM_NOTES_DUE_DATE_META_KEY, $due_date );
}
add_action( 'save_post_usm_note', 'usm_notes_save_due_date', 10, 2 );

/**
 * Переводит заметку в draft при ошибке валидации даты.
 *
 * @param int $post_id ID записи.
 */
function usm_notes_force_note_to_draft( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post || 'usm_note' !== $post->post_type || 'draft' === $post->post_status ) {
		return;
	}

	remove_action( 'save_post_usm_note', 'usm_notes_save_due_date', 10 );
	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'draft',
		)
	);
	add_action( 'save_post_usm_note', 'usm_notes_save_due_date', 10, 2 );
}

/**
 * Добавляет флаг ошибки в URL редиректа после сохранения.
 *
 * @param string $location URL редиректа.
 * @return string
 */
function usm_notes_add_error_query_arg( $location ) {
	return add_query_arg( 'usm_notes_due_date_error', '1', $location );
}

/**
 * Показывает уведомление об ошибке в админке.
 */
function usm_notes_show_due_date_error_notice() {
	if ( ! is_admin() || ! isset( $_GET['usm_notes_due_date_error'] ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->id, array( 'usm_note', 'edit-usm_note' ), true ) ) {
		return;
	}

	$error_message = get_transient( USM_NOTES_ERROR_TRANSIENT_KEY . get_current_user_id() );

	if ( ! $error_message ) {
		return;
	}

	delete_transient( USM_NOTES_ERROR_TRANSIENT_KEY . get_current_user_id() );

	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html( $error_message )
	);
}
add_action( 'admin_notices', 'usm_notes_show_due_date_error_notice' );

/**
 * Добавляет колонку Due Date в список заметок.
 *
 * @param array $columns Колонки таблицы.
 * @return array
 */
function usm_notes_add_due_date_admin_column( $columns ) {
	$new_columns = array();

	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;

		if ( 'title' === $key ) {
			$new_columns['usm_notes_due_date'] = __( 'Due Date', 'usm-notes' );
		}
	}

	return $new_columns;
}
add_filter( 'manage_usm_note_posts_columns', 'usm_notes_add_due_date_admin_column' );

/**
 * Заполняет колонку Due Date в админке.
 *
 * @param string $column  Ключ колонки.
 * @param int    $post_id ID записи.
 */
function usm_notes_render_due_date_admin_column( $column, $post_id ) {
	if ( 'usm_notes_due_date' !== $column ) {
		return;
	}

	$due_date = get_post_meta( $post_id, USM_NOTES_DUE_DATE_META_KEY, true );

	if ( ! $due_date ) {
		echo '&#8212;';

		return;
	}

	echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $due_date ) ) );
}
add_action( 'manage_usm_note_posts_custom_column', 'usm_notes_render_due_date_admin_column', 10, 2 );

/**
 * Делает колонку Due Date сортируемой.
 *
 * @param array $columns Список сортируемых колонок.
 * @return array
 */
function usm_notes_make_due_date_sortable( $columns ) {
	$columns['usm_notes_due_date'] = 'usm_notes_due_date';

	return $columns;
}
add_filter( 'manage_edit-usm_note_sortable_columns', 'usm_notes_make_due_date_sortable' );

/**
 * Настраивает сортировку по колонке Due Date.
 *
 * @param WP_Query $query Объект запроса.
 */
function usm_notes_sort_by_due_date( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'usm_note' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( 'usm_notes_due_date' !== $query->get( 'orderby' ) ) {
		return;
	}

	$query->set( 'meta_key', USM_NOTES_DUE_DATE_META_KEY );
	$query->set( 'orderby', 'meta_value' );
}
add_action( 'pre_get_posts', 'usm_notes_sort_by_due_date' );

/**
 * Шорткод [usm_notes priority="X" before_date="YYYY-MM-DD"].
 *
 * @param array $atts Атрибуты шорткода.
 * @return string
 */
function usm_notes_shortcode_handler( $atts ) {
	$atts = shortcode_atts(
		array(
			'priority'    => '',
			'before_date' => '',
		),
		$atts,
		'usm_notes'
	);

	$priority    = sanitize_title( $atts['priority'] );
	$before_date = sanitize_text_field( $atts['before_date'] );

	$meta_query = array();
	$tax_query  = array();

	if ( '' !== $before_date ) {
		if ( ! usm_notes_is_valid_date( $before_date ) ) {
			return '<div class="usm-notes usm-notes--error">' . esc_html__( 'Неверный формат даты в параметре before_date. Используйте YYYY-MM-DD.', 'usm-notes' ) . '</div>';
		}

		$meta_query[] = array(
			'key'     => USM_NOTES_DUE_DATE_META_KEY,
			'value'   => $before_date,
			'compare' => '<=',
			'type'    => 'DATE',
		);
	}

	if ( '' !== $priority ) {
		$tax_query[] = array(
			'taxonomy' => 'usm_priority',
			'field'    => 'slug',
			'terms'    => $priority,
		);
	}

	$query_args = array(
		'post_type'      => 'usm_note',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => USM_NOTES_DUE_DATE_META_KEY,
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	);

	if ( ! empty( $meta_query ) ) {
		$query_args['meta_query'] = $meta_query;
	}

	if ( ! empty( $tax_query ) ) {
		$query_args['tax_query'] = $tax_query;
	}

	$query = new WP_Query( $query_args );

	if ( ! $query->have_posts() ) {
		wp_reset_postdata();

		return '<div class="usm-notes usm-notes--empty">' . esc_html__( 'Нет заметок с заданными параметрами', 'usm-notes' ) . '</div>';
	}

	wp_enqueue_style( 'usm-notes-frontend' );

	ob_start();
	?>
	<div class="usm-notes">
		<ul class="usm-notes__list">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_id        = get_the_ID();
				$due_date_value = get_post_meta( $post_id, USM_NOTES_DUE_DATE_META_KEY, true );
				$priority_terms = get_the_terms( $post_id, 'usm_priority' );
				$priority_label = __( 'No priority', 'usm-notes' );

				if ( is_array( $priority_terms ) && ! empty( $priority_terms ) ) {
					$priority_label = $priority_terms[0]->name;
				}
				?>
				<li class="usm-notes__item">
					<h3 class="usm-notes__title"><?php the_title(); ?></h3>
					<div class="usm-notes__meta">
						<span><strong><?php esc_html_e( 'Priority:', 'usm-notes' ); ?></strong> <?php echo esc_html( $priority_label ); ?></span>
						<span><strong><?php esc_html_e( 'Due Date:', 'usm-notes' ); ?></strong>
							<?php echo $due_date_value ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $due_date_value ) ) ) : '&#8212;'; ?>
						</span>
					</div>
					<div class="usm-notes__content"><?php echo wp_kses_post( wpautop( get_the_excerpt() ? get_the_excerpt() : get_the_content() ) ); ?></div>
				</li>
				<?php
			}
			?>
		</ul>
	</div>
	<?php
	wp_reset_postdata();

	return ob_get_clean();
}
add_shortcode( 'usm_notes', 'usm_notes_shortcode_handler' );

/**
 * Виджет для отображения ближайших заметок.
 */
class USM_Notes_Widget extends WP_Widget {
	/**
	 * Конструктор.
	 */
	public function __construct() {
		parent::__construct(
			'usm_notes_widget',
			__( 'USM Notes Widget', 'usm-notes' ),
			array(
				'description' => __( 'Shows upcoming notes with due dates.', 'usm-notes' ),
			)
		);
	}

	/**
	 * Выводит виджет на фронтенде.
	 *
	 * @param array $args     Аргументы виджета.
	 * @param array $instance Настройки виджета.
	 */
	public function widget( $args, $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Notes', 'usm-notes' );
		$count = isset( $instance['count'] ) ? (int) $instance['count'] : 5;
		$count = $count > 0 ? $count : 5;

		$query = new WP_Query(
			array(
				'post_type'      => 'usm_note',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
				'meta_key'       => USM_NOTES_DUE_DATE_META_KEY,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);

		echo wp_kses_post( $args['before_widget'] );

		if ( ! empty( $title ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		if ( $query->have_posts() ) {
			echo '<ul class="usm-notes-widget">';

			while ( $query->have_posts() ) {
				$query->the_post();

				$due_date_value = get_post_meta( get_the_ID(), USM_NOTES_DUE_DATE_META_KEY, true );
				echo '<li class="usm-notes-widget__item">';
				echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';

				if ( $due_date_value ) {
					echo '<small> (' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $due_date_value ) ) ) . ')</small>';
				}

				echo '</li>';
			}

			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'Нет заметок с заданными параметрами', 'usm-notes' ) . '</p>';
		}

		wp_reset_postdata();

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Форма настроек виджета.
	 *
	 * @param array $instance Текущие настройки.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Upcoming Notes', 'usm-notes' );
		$count = isset( $instance['count'] ) ? (int) $instance['count'] : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'usm-notes' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of notes:', 'usm-notes' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" step="1" value="<?php echo esc_attr( $count ); ?>" />
		</p>
		<?php
	}

	/**
	 * Сохраняет настройки виджета.
	 *
	 * @param array $new_instance Новые данные.
	 * @param array $old_instance Старые данные.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['count'] = isset( $new_instance['count'] ) ? max( 1, (int) $new_instance['count'] ) : 5;

		return $instance;
	}
}

/**
 * Регистрирует виджет.
 */
function usm_notes_register_widget() {
	register_widget( 'USM_Notes_Widget' );
}
add_action( 'widgets_init', 'usm_notes_register_widget' );
