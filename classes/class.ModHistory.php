<?php

/**
 * Diff an array of postmeta values
 */
if ( ! function_exists( 'array_diff_meta' ) ) {
	function array_diff_meta( $array1, $array2 ) {
		if ( ! is_array( $array1 ) || ! is_array( $array2 ) ) return false;
		$return = array();
		foreach ( $array1 as $key => $val ) {
			if ( isset( $array2[ $key ] ) && is_array( $array2[ $key ] ) && is_array( $val ) ) {
				if ( $array2[ $key ][0] != $val[0] ) $return[ $key ] = $val[0];
			}
		}
		return $return;
	}
}

/**
 * Add modification history metabox to post edit screen
 */
function wp_modification_history_metabox() {
	add_meta_box( 'wp_modification_history', __( 'Modification History', 'afk-travel' ), 'wp_modification_history', 'post' );
	add_meta_box( 'wp_modification_history', __( 'Modification History', 'afk-travel' ), 'wp_modification_history', 'hotel' );
	add_meta_box( 'wp_modification_history', __( 'Modification History', 'afk-travel' ), 'wp_modification_history', 'restaurant' );
}
add_action( 'add_meta_boxes', 'wp_modification_history_metabox' );

/**
 * Hours of operation meta box HTML
 */
function wp_modification_history() {
	global $post, $wpdb;

	// Nonce
	wp_nonce_field( 'modification_history', 'modification_history' );

	// Get our modifications
	$mods = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}modifications WHERE `post_id` = " . absint( $post->ID ) . " ORDER BY `modified`" );
	if ( ! empty( $mods ) ) {
	?>
	<table>
		<thead>
			<tr style="text-align:left;">
				<th>User</th>
				<th colspan="2">Date</th>
				<th>Modifications</th>
			</tr>
		</thead>
		<tbody>
			<?php
				$last_time = false;
				foreach ( $mods as $mod ) {
					$user = get_user_by( 'id', $mod->user_id );
					$user = $user ? $user->data->display_name : '';

					// Build modifications arrays for posts table
					$posts_modified = array(
						'before'	=> (array) unserialize( $mod->posts_before ),
						'after'		=> (array) unserialize( $mod->posts_after ),
					);
					unset( $posts_modified['after']['post_modified'] );
					unset( $posts_modified['after']['post_modified_gmt'] );

					// Build modifications arrays for postmeta table
					$postmeta_modified = array(
						'before'	=> (array) unserialize( $mod->postmeta_before ),
						'after'		=> (array) unserialize( $mod->postmeta_after ),
					);
					unset( $postmeta_modified['after']['post_modified'] );
					unset( $postmeta_modified['after']['post_modified_gmt'] );

					// Build array of differences for each table modifications array
					$posts_mods = array_diff_assoc( $posts_modified['after'], $posts_modified['before'] );
					$postmeta_mods = array_diff_assoc( $postmeta_modified['after'], $postmeta_modified['before'] );
					echo '<tr>';
						echo '<td style="vertical-align:top;">' . str_replace( ' ', '&nbsp;', $user ) . '</td>';
						echo '<td style="vertical-align:top;">' . date( 'n/j/y', strtotime( $mod->modified ) ) . '</td>';
						echo '<td style="vertical-align:top;">';
						if ( ! empty( $posts_mods ) ) {
							foreach ( $posts_mods as $key => $postmod ) {
								// Optionally display the time if it's different than the last diff
								if ( date( 'g:ia', strtotime( $mod->modified ) ) != $last_time ) {
									$last_time = date( 'g:ia', strtotime( $mod->modified ) );
									echo $last_time . '</td><td style="vertical-align:top;width:100%;">';
								} else {
									// echo date( 'g:ia', strtotime( $mod->modified ) ) . ' == ' . $last_time;
								}
								echo '<h4 style="margin:0;padding:0 6px;background:#eee;box-shadow:0 1px 3px 0px rgba(50, 50, 50, 0.25);cursor:pointer;position:relative;" class="togglenext">' . $key . '<div style="color:#aaa;position:absolute;right:0;" class="dashicons dashicons-arrow-down"></div></h4>';
								echo '<div style="display:none;">' . wp_text_diff( $posts_modified['before'][ $key ], $postmod ) . '</div>';
							}
						} else if ( date( 'g:ia', strtotime( $mod->modified ) ) != $last_time ) {
							$last_time = date( 'g:ia', strtotime( $mod->modified ) );
							echo $last_time . '</td><td style="vertical-align:top;width:100%;">';
						}
						if ( ! empty( $postmeta_mods ) ) {
							foreach ( $postmeta_mods as $key => $metamod ) {
								echo '<h4 style="margin:0;padding:0 6px;background:#eee;box-shadow:0 1px 3px 0px rgba(50, 50, 50, 0.25);cursor:pointer;position:relative;" class="togglenext">' . $key . '<div style="color:#aaa;position:absolute;right:0;" class="dashicons dashicons-arrow-down"></div></h4>';
								if ( ! isset( $post_modified['before'][ $key ] ) ) {
									echo '<div style="display:none;">Set value to <strong>' . $metamod[0] . '</strong></div>';
								} else {
									$diff = wp_text_diff( $postmeta_modified['before'][ $key ], $metamod );
									echo '<div style="display:none;">' . wp_text_diff( $postmeta_modified['before'][ $key ], $metamod ) . '</div>';
								}
							}
						} else {
							$diff = array_diff_meta( $postmeta_modified['after'], $postmeta_modified['before'] );
							if ( ! empty( $diff ) ) {
								foreach ( $diff as $key => $change ) {
									echo '<h4 style="margin:0;padding:0 6px;background:#eee;box-shadow:0 1px 3px 0px rgba(50, 50, 50, 0.25);cursor:pointer;" class="togglenext">' . $key . '<div style="float:right;color:#aaa;clear:right;" class="dashicons dashicons-arrow-down"></div></h4>';
									echo '<div style="display:none;">' . wp_text_diff( $postmeta_modified['before'][$key][0], $change ) . '</div>';
								}
							}
						}
						if ( empty( $posts_mods ) && empty( $postmeta_mods ) ) {
							echo '<em>Updated with no modifications</em>';
						}
						echo '</td>';
					echo '</tr>';
				}
			?>
		</tbody>
	</table>
	<?php
	} else {
		echo '<h2>No modification history is available for this post.</h2>';
	}
	?>

	<script>
		jQuery(document).ready(function($) {
			$('h4.togglenext').on('click', function() {
				$(this).next().stop().slideToggle();
				if ( $(this).children('.dashicons-arrow-down').length ) {
					$(this).children('.dashicons-arrow-down').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
				} else {
					$(this).children('.dashicons-arrow-up').removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
				}
			});
		});
	</script>

	<?php
}


/**
 * Before post save hook to track modification history
 */
$wp_modification_history = false;
function wp_modification_history_save( $post_id ) {

	global $wp_modification_history;

	// Checks save status
	$is_autosave = wp_is_post_autosave( $post_id );
	$is_revision = wp_is_post_revision( $post_id );
	$is_valid_nonce = ( isset( $_POST[ 'modification_history' ] ) && wp_verify_nonce( $_POST[ 'modification_history' ], 'modification_history' ) ) ? true : false;
 
	// Exits script depending on save status
	if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
		return;
	}

	// Get current data from posts table
	$wp_modification_history = array(
		'before'	=> array(
			'posts' 	=> get_post( $post_id ),
			'postmeta' 	=> get_post_meta( $post_id ),
		),
	);
	unset( $wp_modification_history['before']['postmeta']['_encloseme'] );

}
add_action( 'pre_post_update', 'wp_modification_history_save' );

/**
 * After post save hook to track modification history
 */
function wp_posts_modifications_saved( $post_id ) {

	global $wp_modification_history;

	// Checks save status
	$is_autosave = wp_is_post_autosave( $post_id );
	$is_revision = wp_is_post_revision( $post_id );
	$is_valid_nonce = ( isset( $_POST[ 'modification_history' ] ) && wp_verify_nonce( $_POST[ 'modification_history' ], 'modification_history' ) ) ? true : false;
 
	// Exits script depending on save status
	if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
		return;
	}

	// Update the modification history
	$wp_modification_history['after'] = array(
		'posts' 	=> get_post( $post_id )
	);

}
add_action( 'post_updated', 'wp_posts_modifications_saved' );

/**
 * After postmeta save hook to track modification history (and insert into DB when differences are found)
 */
function wp_postmeta_modifications_saved( $post_id ) {

	global $wp_modification_history, $wpdb, $current_user;
	get_currentuserinfo();

	// $post_id = $wpdb->get_var( "SELECT `post_id` FROM {$wpdb->postmeta} WHERE `meta_id` = {$meta_id}" );

	// Checks save status
	$is_autosave = wp_is_post_autosave( $post_id );
	$is_revision = wp_is_post_revision( $post_id );
	$is_valid_nonce = ( isset( $_POST[ 'modification_history' ] ) && wp_verify_nonce( $_POST[ 'modification_history' ], 'modification_history' ) ) ? true : false;
 
	// Exits script depending on save status
	if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
		return;
	}

	// Update the modification history
	$wp_modification_history['after']['posts'] = get_post( $post_id );
	$wp_modification_history['after']['postmeta'] = get_post_meta( $post_id );
	unset( $wp_modification_history['after']['postmeta']['_encloseme'] );

	// Insert modifications into modification table
	$diff_posts = array_diff_assoc( (array) $wp_modification_history['after']['posts'], (array) $wp_modification_history['before']['posts'] );
	$diff_postmeta = array_diff_assoc( $wp_modification_history['after']['postmeta'], $wp_modification_history['before']['postmeta'] );
	if ( ! empty( $diff_posts ) || ! empty( $diff_postmeta ) ) {
		$wpdb->insert(
			$wpdb->prefix . 'modifications',
			array(
				'post_id'			=> $post_id,
				'user_id'			=> $current_user->ID,
				'posts_before'		=> serialize( $wp_modification_history['before']['posts'] ),
				'postmeta_before'	=> serialize( $wp_modification_history['before']['postmeta'] ),
				'posts_after'		=> serialize( $wp_modification_history['after']['posts'] ),
				'postmeta_after'	=> serialize( $wp_modification_history['after']['postmeta'] ),
				'modified'			=> current_time( 'Y-m-d H:i:s' ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

}
add_action( 'wp_insert_post', 'wp_postmeta_modifications_saved', 99999 );
// add_action( 'updated_postmeta', 'wp_postmeta_modifications_saved' );