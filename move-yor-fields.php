<?php
/*
Plugin Name: Move Yor Fields
Description: Move values between fields - in-built, custom post meta, acf etc.
Version: 0.1
Author: Szymon Polaczy - Get Over Online
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; //Exit if accessed directrly
}

if ( ! class_exists( 'Move_Yor_Fields' ) ) {
	class Move_Yor_Fields {
		public function __construct() {
			//add_action( 'acf/init', array( $this, '' ) );
            add_action('admin_menu', [$this, 'add_options_page']);
            add_action('wp_ajax_move_yor_fields', [$this, 'move_yor_fields_ajax']);
		}

        function add_options_page() {
            add_menu_page(
                'Move Yor Fields',
                'Move Yor Fields',
                'manage_options',
                'move_yor_fields',
                [$this, 'move_yor_fields_page']
            );
        }

        function move_yor_fields_page() {
            ?>
            <div class="wrap">
                <h2>Move Yor Fields</h2>
				<form id="postmeta-copier-form">
					<label for="source-type">Source type:</label>
					<select name="source_type" id="source-type">
						<option value="featured">Featured Image</option>
						<option value="title">Post Title</option>
						<option value="custom">Custom Meta</option>
					</select>

					<br>

					<label for="source-post-type">Post Type:</label>
					<select id="source-post-type" name="source_post_type">
						<?php
						$post_types = get_post_types( array(), 'objects' );
						foreach ( $post_types as $post_type ):
						?>
							<option value="<?php echo esc_attr( $post_type->name ); ?>">
								<?php echo esc_html( $post_type->label ); ?>
							</option>
							<?php
						endforeach;
						?>
					</select>

                    <br>

                    <label for="source-meta">Source Postmeta Name:</label>
                    <input type="text" id="source-meta" name="source_meta">

                    <br>

                    <label for="target-meta">Target Postmeta Name:</label>
                    <input type="text" id="target-meta" name="target_meta" required>

                    <br>

                    <label for="empty-original-value">Empty original value:</label>
                    <input type="checkbox" id="empty-original-value" name="empty_original_value">

                    <br>


                    <input type="submit" class="button button-primary" value="Copy Postmeta">
                </form>
                <div id="progress-container" style="display: none;">
                    <p>Copying in progress...</p>
                    <progress id="progress-bar" value="0" max="100"></progress>
                </div>
            </div>

            <script>
                jQuery(document).ready(function ($) {
                    $('#postmeta-copier-form').submit(function (e) {
                        e.preventDefault();

						const sourceType = document.querySelector('#source-type').value;
						const sourcePostType = document.querySelector('#source-post-type').value;
						const emptyOriginalValue = document.querySelector('#empty-original-value').checked;
                        var sourceMeta = $('#source-meta').val();
                        var targetMeta = $('#target-meta').val();

                        $('#progress-container').show();

                        // AJAX request to handle the postmeta copy
                        $.ajax({
                            type: 'POST',
                            url: ajaxurl,
                            data: {
                                action: 'move_yor_fields',
								source_type: sourceType,
								source_post_type: sourcePostType,
                                source_meta: sourceMeta,
                                target_meta: targetMeta,
                                empty_original_value: emptyOriginalValue,
                                nonce: '<?php echo wp_create_nonce('move_yor_fields_nonce'); ?>',
                            },
                            success: function (response) {
                                $('#progress-container').hide();
                                alert(response);
                            },
                            xhrFields: {
                                onprogress: function (e) {
                                    if (e.lengthComputable) {
                                        var percentComplete = (e.loaded / e.total) * 100;
                                        $('#progress-bar').val(percentComplete);
                                    }
                                },
                            },
                            cache: false,
                        });
                    });
                });
            </script>
            <?php
        }

        function move_yor_fields_ajax() {
            check_ajax_referer('move_yor_fields_nonce', 'nonce');

			$source_type = sanitize_text_field($_POST['source_type']);
			$source_post_type = sanitize_text_field($_POST['source_post_type']);
            $source_meta = sanitize_text_field($_POST['source_meta']);
            $target_meta = sanitize_text_field($_POST['target_meta']);
            $empty_original_value = sanitize_text_field($_POST['empty_original_value']);

            // Perform the postmeta copy logic here
            // For example:
            $args = array(
                'post_type' => $source_post_type,
                'posts_per_page' => -1,
            );
            $posts = get_posts($args);
            foreach ($posts as $post) {
				switch($source_type) {
					case "featured":
						$source_value = get_post_meta($post->ID, '_thumbnail_id', true);

                        if ($empty_original_value) {
                            delete_post_meta($post->ID, '_thumbnail_id');
                        }
						break;
					case "title":
						$source_value = $post->post_title;

                        if ($empty_original_value) {
                            wp_update_post(array('ID' => $post->ID, 'post_title' => ''));
                        }
						break;
					default:
						$source_value = get_post_meta($post->ID, $source_meta, true);

                        if ($empty_original_value) {
                            delete_post_meta($post->ID, $source_meta);
                        }
						break;
				}

				$source_value = apply_filters('myf/filter_source_value', $source_value, $source_type, $source_meta, $target_meta, $post);	

                $target_post_id = $post->ID;
                $target_post_id = apply_filters('myf/change_target_post', $source_value, $source_type, $source_meta, $target_meta, $post);	

				//todo: add checkbox if you want to use in-built meta or acf
				update_post_meta($target_post_id, $target_meta, $source_value);
				//echo var_dump(update_field($target_meta, $source_value, $post->ID));
				//todo: error validation - add filter here for good and bad things
            }

            echo 'Postmeta copied successfully!';
            wp_die();
        }
	}

	$move_fields_plugin = new Move_Yor_Fields();
} else {
	add_action( 'admin_notices', function() {
		?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Plugin Move Yor Fields is doing nothing right now as a class named Move_Yor_Fields already exists' , 'move-yor-acf' ); ?>
			</p>
		</div>
		<?php
	});
}
