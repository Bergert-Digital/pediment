<?php
/**
 * File fields: upload checks, private storage, the capability-checked
 * download, and clean-up when a submission is deleted.
 *
 * A `pediment/form-field` with `fieldType: "file"` lets a visitor attach
 * documents (a CV, for example). The browser then posts the form as
 * multipart/form-data, with the files under `files[<field name>][]`.
 *
 * Storage is private. Files go to a folder with a random name below the
 * uploads directory (or the path the `pediment_form_files_dir` filter
 * returns, for a folder outside the web root). The folder carries an
 * `.htaccess` that refuses every request, and each submission gets a
 * second random folder level, so nginx, which ignores `.htaccess`, has no
 * URL anyone can guess. People read the files only through the download in
 * wp-admin, which checks the capability to edit the submission.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File extensions a file field may accept at most. A field narrows this list;
 * the `pediment_form_file_extensions` filter can change it for a site. Never
 * add a type a browser renders as a page (html, svg, xml) or a server runs.
 */
const PEDIMENT_FORM_FILE_EXTENSIONS = array( 'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'jpg', 'jpeg', 'png' );

/** Extensions a file field accepts when it declares none. */
const PEDIMENT_FORM_FILE_DEFAULT_TYPES = 'pdf';

/** Maximum size per file in MB when a field declares none. */
const PEDIMENT_FORM_FILE_DEFAULT_MB = 10;

/** Maximum number of files one field with `multiple` accepts. */
const PEDIMENT_FORM_FILE_MAX_COUNT = 5;

/** Option that holds the random part of the private folder name. */
const PEDIMENT_FORM_FILES_DIR_OPTION = 'pediment_form_files_dir';

/** Submission meta with the stored files, keyed by field name. */
const PEDIMENT_FORM_FILES_META = '_files';

/**
 * The extensions a file field accepts: its own list, limited to the safe list.
 *
 * @param mixed $raw The field's `fileTypes` attribute: "pdf, docx", ".pdf .docx" or a list.
 * @return array<int,string> Lowercase extensions without dots; never empty.
 */
function pediment_form_file_extensions( $raw ): array {
	$allowed = array_map( 'strtolower', (array) apply_filters( 'pediment_form_file_extensions', PEDIMENT_FORM_FILE_EXTENSIONS ) );
	$parts   = is_array( $raw ) ? $raw : preg_split( '/[\s,;|]+/', (string) $raw );
	$out     = array();
	foreach ( (array) $parts as $part ) {
		$ext = strtolower( ltrim( trim( (string) $part ), '.' ) );
		if ( '' !== $ext && in_array( $ext, $allowed, true ) && ! in_array( $ext, $out, true ) ) {
			$out[] = $ext;
		}
	}
	if ( array() === $out ) {
		$out = array_values( array_intersect( array( PEDIMENT_FORM_FILE_DEFAULT_TYPES ), $allowed ) );
	}
	return array() !== $out ? $out : array( PEDIMENT_FORM_FILE_DEFAULT_TYPES );
}

/**
 * The largest file a field accepts, in bytes: its own limit, capped by what
 * PHP accepts (upload_max_filesize, post_max_size).
 *
 * @param mixed $mb The field's `maxSize` attribute in MB.
 * @return int
 */
function pediment_form_file_max_bytes( $mb ): int {
	$mb    = is_numeric( $mb ) && (float) $mb > 0 ? (float) $mb : PEDIMENT_FORM_FILE_DEFAULT_MB;
	$bytes = (int) round( $mb * MB_IN_BYTES );
	$php   = (int) wp_max_upload_size();
	return $php > 0 ? min( $bytes, $php ) : $bytes;
}

/**
 * A byte count for people: "10 MB", "1.5 MB", "300 KB".
 *
 * @param int $bytes Byte count.
 * @return string
 */
function pediment_form_file_size_label( int $bytes ): string {
	return (string) size_format( $bytes, $bytes >= MB_IN_BYTES && 0 !== $bytes % MB_IN_BYTES ? 1 : 0 );
}

/**
 * The hint under a file field: "PDF, DOCX · up to 10 MB".
 *
 * @param array<int,string> $extensions Accepted extensions.
 * @param int               $max_bytes  Size limit per file.
 * @return string
 */
function pediment_form_file_hint( array $extensions, int $max_bytes ): string {
	return sprintf(
		/* translators: 1: accepted file types, e.g. "PDF, DOCX"; 2: size limit, e.g. "10 MB". */
		__( '%1$s · up to %2$s', 'pediment' ),
		strtoupper( implode( ', ', $extensions ) ),
		pediment_form_file_size_label( $max_bytes )
	);
}

/**
 * Extension pattern => MIME type for the given extensions, in the shape
 * wp_check_filetype_and_ext() expects.
 *
 * @param array<int,string> $extensions Accepted extensions.
 * @return array<string,string>
 */
function pediment_form_file_mimes( array $extensions ): array {
	$mimes = array();
	foreach ( wp_get_mime_types() as $pattern => $mime ) {
		if ( array() !== array_intersect( explode( '|', $pattern ), $extensions ) ) {
			$mimes[ $pattern ] = $mime;
		}
	}
	return $mimes;
}

/**
 * Turn the request's file parameters into field name => list of uploads.
 * Accepts the PHP shape of `files[<name>][]` and of `files[<name>]`. Slots
 * without a file (UPLOAD_ERR_NO_FILE) are dropped.
 *
 * @param array<string,mixed> $file_params WP_REST_Request::get_file_params().
 * @return array<string,array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>>
 */
function pediment_form_normalize_uploads( array $file_params ): array {
	$files = isset( $file_params['files'] ) && is_array( $file_params['files'] ) ? $file_params['files'] : array();
	$names = isset( $files['name'] ) && is_array( $files['name'] ) ? $files['name'] : array();
	$out   = array();
	foreach ( $names as $field => $list ) {
		$field = (string) $field;
		$multi = is_array( $list );
		foreach ( $multi ? array_keys( $list ) : array( null ) as $i ) {
			$get   = static function ( string $prop ) use ( $files, $field, $i ) {
				$value = $files[ $prop ][ $field ] ?? null;
				return null === $i ? $value : ( is_array( $value ) ? ( $value[ $i ] ?? null ) : null );
			};
			$error = (int) $get( 'error' );
			if ( UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}
			$out[ $field ][] = array(
				'name'     => (string) $get( 'name' ),
				'type'     => (string) $get( 'type' ),
				'tmp_name' => (string) $get( 'tmp_name' ),
				'error'    => $error,
				'size'     => (int) $get( 'size' ),
			);
		}
	}
	return $out;
}

/**
 * Whether a temporary file came from this request's upload. The filter exists
 * for tests, which cannot make PHP see a real upload.
 *
 * @param string $tmp_name Temporary path.
 * @return bool
 */
function pediment_form_is_uploaded_file( string $tmp_name ): bool {
	return (bool) apply_filters( 'pediment_form_is_uploaded_file', '' !== $tmp_name && is_uploaded_file( $tmp_name ), $tmp_name );
}

/**
 * Check every file field's uploads: count, required, upload errors, size,
 * real file type and name.
 *
 * @param array<int,array<string,mixed>>                 $fields  Field definitions (pediment_form_collect_fields()).
 * @param array<string,array<int,array<string,mixed>>> $uploads pediment_form_normalize_uploads() output.
 * @return array{errors:array<string,string>,files:array<string,array<int,array{name:string,type:string,tmp_name:string,size:int}>>}
 */
function pediment_form_validate_files( array $fields, array $uploads ): array {
	$errors = array();
	$clean  = array();
	foreach ( $fields as $field ) {
		if ( 'file' !== ( $field['type'] ?? '' ) ) {
			continue;
		}
		$name  = (string) $field['name'];
		$list  = $uploads[ $name ] ?? array();
		$exts  = (array) ( $field['file_types'] ?? array( PEDIMENT_FORM_FILE_DEFAULT_TYPES ) );
		$max   = (int) ( $field['max_size'] ?? pediment_form_file_max_bytes( PEDIMENT_FORM_FILE_DEFAULT_MB ) );
		$multi = ! empty( $field['multiple'] );

		if ( array() === $list ) {
			if ( ! empty( $field['required'] ) ) {
				/* translators: %s: field label */
				$errors[ $name ] = sprintf( __( '%s is required.', 'pediment' ), $field['label'] );
			}
			continue;
		}
		if ( ! $multi && count( $list ) > 1 ) {
			$errors[ $name ] = __( 'Attach one file only.', 'pediment' );
			continue;
		}
		if ( count( $list ) > PEDIMENT_FORM_FILE_MAX_COUNT ) {
			/* translators: %d: maximum number of files */
			$errors[ $name ] = sprintf( __( 'Attach at most %d files.', 'pediment' ), PEDIMENT_FORM_FILE_MAX_COUNT );
			continue;
		}

		$too_large = sprintf(
			/* translators: %s: size limit, e.g. "10 MB" */
			__( 'The file is too large. The limit is %s.', 'pediment' ),
			pediment_form_file_size_label( $max )
		);
		$bad_type  = sprintf(
			/* translators: %s: accepted file types, e.g. "PDF, DOCX" */
			__( 'This file type is not accepted. Use %s.', 'pediment' ),
			strtoupper( implode( ', ', $exts ) )
		);
		$mimes     = pediment_form_file_mimes( $exts );
		$accepted  = array();
		foreach ( $list as $upload ) {
			$error = (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE );
			if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error || (int) $upload['size'] > $max ) {
				$errors[ $name ] = $too_large;
				continue 2;
			}
			$tmp = (string) ( $upload['tmp_name'] ?? '' );
			if ( UPLOAD_ERR_OK !== $error || ! pediment_form_is_uploaded_file( $tmp ) || ! is_file( $tmp ) ) {
				$errors[ $name ] = __( 'The file could not be uploaded. Please try again.', 'pediment' );
				continue 2;
			}

			$original = (string) ( $upload['name'] ?? '' );
			$check    = wp_check_filetype_and_ext( $tmp, $original, $mimes );
			$filename = ! empty( $check['proper_filename'] ) ? (string) $check['proper_filename'] : $original;
			$ext      = strtolower( (string) ( $check['ext'] ?? '' ) );
			if ( '' === $ext || empty( $check['type'] ) || ! in_array( $ext, $exts, true ) ) {
				$errors[ $name ] = $bad_type;
				continue 2;
			}

			$base = sanitize_file_name( (string) pathinfo( $filename, PATHINFO_FILENAME ) );
			$base = '' !== $base && 'unnamed-file' !== $base ? mb_substr( $base, 0, 100 ) : 'file';

			$accepted[] = array(
				'name'     => $base . '.' . $ext,
				'type'     => (string) $check['type'],
				'tmp_name' => $tmp,
				'size'     => (int) $upload['size'],
			);
		}
		$clean[ $name ] = $accepted;
	}
	return array(
		'errors' => $errors,
		'files'  => $clean,
	);
}

/**
 * The private folder for uploaded files, created on first use with a deny-all
 * `.htaccess` and an empty index. Returns '' when it cannot be created.
 *
 * @return string Absolute path without a trailing slash.
 */
function pediment_form_files_base_dir(): string {
	$token = (string) get_option( PEDIMENT_FORM_FILES_DIR_OPTION, '' );
	if ( ! preg_match( '/^[a-z0-9]{32}$/', $token ) ) {
		$token = strtolower( wp_generate_password( 32, false, false ) );
		update_option( PEDIMENT_FORM_FILES_DIR_OPTION, $token, false );
	}
	$uploads = wp_upload_dir( null, false );
	$default = empty( $uploads['error'] ) ? trailingslashit( (string) $uploads['basedir'] ) . 'pediment-forms-' . $token : '';

	/**
	 * Filters the folder that stores files uploaded through forms. Return a
	 * path outside the web root to keep the files off the web server entirely.
	 *
	 * @param string $dir Default: a random-named folder in the uploads directory.
	 */
	$dir = untrailingslashit( (string) apply_filters( 'pediment_form_files_dir', $default ) );
	if ( '' === $dir || ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) ) {
		return '';
	}
	pediment_form_files_protect( $dir );
	return $dir;
}

/**
 * Put a deny-all `.htaccess` and an empty `index.php` into a folder.
 *
 * @param string $dir Absolute folder path.
 */
function pediment_form_files_protect( string $dir ): void {
	$guards = array(
		'.htaccess' => "# Pediment form uploads: no direct access.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
		'index.php' => "<?php\n// Silence is golden.\n",
	);
	foreach ( $guards as $file => $content ) {
		$path = $dir . '/' . $file;
		if ( ! file_exists( $path ) ) {
			file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny guard file next to private uploads; WP_Filesystem may need credentials on the front end.
		}
	}
}

/**
 * Move a submission's checked uploads into private storage and record them
 * in the submission's `_files` meta.
 *
 * @param int                                          $submission_id form_submission post id.
 * @param array<string,array<int,array<string,mixed>>> $files         pediment_form_validate_files()['files'].
 * @return array<string,array<int,array{name:string,path:string,size:int,type:string}>> The stored files.
 */
function pediment_form_store_files( int $submission_id, array $files ): array {
	$base = pediment_form_files_base_dir();
	if ( '' === $base || array() === array_filter( $files ) ) {
		return array();
	}
	$folder = $submission_id . '-' . strtolower( wp_generate_password( 24, false, false ) );
	$dir    = $base . '/' . $folder;
	if ( ! wp_mkdir_p( $dir ) ) {
		return array();
	}
	pediment_form_files_protect( $dir );

	$stored = array();
	foreach ( $files as $field => $list ) {
		foreach ( (array) $list as $file ) {
			$tmp  = (string) ( $file['tmp_name'] ?? '' );
			$name = wp_unique_filename( $dir, (string) ( $file['name'] ?? 'file' ) );
			$path = $dir . '/' . $name;
			$done = is_uploaded_file( $tmp ) ? move_uploaded_file( $tmp, $path ) : ( pediment_form_is_uploaded_file( $tmp ) && copy( $tmp, $path ) );
			if ( ! $done ) {
				continue;
			}
			chmod( $path, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private file: owner and group only.
			$stored[ (string) $field ][] = array(
				'name' => $name,
				'path' => $folder . '/' . $name,
				'size' => (int) ( $file['size'] ?? filesize( $path ) ),
				'type' => (string) ( $file['type'] ?? '' ),
			);
		}
	}
	update_post_meta( $submission_id, PEDIMENT_FORM_FILES_META, wp_slash( (string) wp_json_encode( $stored ) ) );
	return $stored;
}

/**
 * The files stored for a submission, each with its absolute path. A record
 * whose path leaves the private folder or whose file is gone is skipped.
 *
 * @param int $submission_id form_submission post id.
 * @return array<string,array<int,array{name:string,path:string,size:int,type:string,abs:string}>>
 */
function pediment_form_submission_files( int $submission_id ): array {
	$decoded = json_decode( (string) get_post_meta( $submission_id, PEDIMENT_FORM_FILES_META, true ), true );
	if ( ! is_array( $decoded ) ) {
		return array();
	}
	$base = pediment_form_files_base_dir();
	$real = '' !== $base ? realpath( $base ) : false;
	if ( false === $real ) {
		return array();
	}
	$out = array();
	foreach ( $decoded as $field => $list ) {
		foreach ( (array) $list as $file ) {
			$abs = realpath( $real . '/' . (string) ( $file['path'] ?? '' ) );
			if ( false === $abs || 0 !== strpos( $abs, $real . DIRECTORY_SEPARATOR ) || ! is_file( $abs ) ) {
				continue;
			}
			$out[ (string) $field ][] = array(
				'name' => (string) ( $file['name'] ?? basename( $abs ) ),
				'path' => (string) $file['path'],
				'size' => (int) ( $file['size'] ?? 0 ),
				'type' => (string) ( $file['type'] ?? '' ),
				'abs'  => $abs,
			);
		}
	}
	return $out;
}

/**
 * The wp-admin download URL of one stored file.
 *
 * @param int    $submission_id form_submission post id.
 * @param string $field         Field name.
 * @param int    $index         Position in the field's file list.
 * @return string
 */
function pediment_form_file_url( int $submission_id, string $field, int $index ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'     => 'pediment_form_file',
				'submission' => $submission_id,
				'field'      => $field,
				'file'       => $index,
			),
			admin_url( 'admin-post.php' )
		),
		'pediment_form_file_' . $submission_id
	);
}

add_action( 'admin_post_pediment_form_file', 'pediment_form_handle_file_download' );

/**
 * Send one stored file to a person who may edit the submission.
 */
function pediment_form_handle_file_download(): void {
	$id    = isset( $_GET['submission'] ) ? absint( wp_unslash( $_GET['submission'] ) ) : 0;
	$field = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';
	$index = isset( $_GET['file'] ) ? absint( wp_unslash( $_GET['file'] ) ) : 0;
	if ( $id <= 0 || PEDIMENT_FORM_CPT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'Not allowed.', 'pediment' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'pediment_form_file_' . $id );

	$file = pediment_form_submission_files( $id )[ $field ][ $index ] ?? null;
	if ( null === $file ) {
		wp_die( esc_html__( 'The file no longer exists.', 'pediment' ), '', array( 'response' => 404 ) );
	}

	nocache_headers();
	header( 'Content-Type: application/octet-stream' );
	$ascii = (string) preg_replace( '/[^A-Za-z0-9._-]+/', '_', $file['name'] );
	header( 'Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $file['name'] ) );
	header( 'Content-Length: ' . (string) filesize( $file['abs'] ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $file['abs'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a private upload to an authorised user.
	exit;
}

add_action( 'before_delete_post', 'pediment_form_delete_files' );

/**
 * Remove a submission's files and their folder when the submission is deleted
 * (by a person or by the retention clean-up).
 *
 * @param int $post_id Post being deleted.
 */
function pediment_form_delete_files( $post_id ): void {
	$post_id = (int) $post_id;
	if ( PEDIMENT_FORM_CPT !== get_post_type( $post_id ) ) {
		return;
	}
	$folders = array();
	foreach ( pediment_form_submission_files( $post_id ) as $list ) {
		foreach ( $list as $file ) {
			wp_delete_file( $file['abs'] );
			$folders[ dirname( $file['abs'] ) ] = true;
		}
	}
	foreach ( array_keys( $folders ) as $dir ) {
		foreach ( array( '.htaccess', 'index.php' ) as $guard ) {
			wp_delete_file( $dir . '/' . $guard );
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- empty per-submission folder; a leftover folder is harmless.
	}
	delete_post_meta( $post_id, PEDIMENT_FORM_FILES_META );
}

/**
 * Attachments for an e-mail destination, or the note that replaces them.
 * Files are attached unless the destination turns it off (`attach_files`
 * false) or they are together larger than the
 * `pediment_form_mail_attachment_limit` (10 MB by default). Then the mail
 * carries a link to the submission in wp-admin instead.
 *
 * @param int                 $submission_id form_submission post id (0 for a test send).
 * @param array<string,mixed> $dest          E-mail destination record.
 * @return array{paths:array<int,string>,note:string}
 */
function pediment_form_mail_attachments( int $submission_id, array $dest ): array {
	$none = array(
		'paths' => array(),
		'note'  => '',
	);
	if ( $submission_id <= 0 ) {
		return $none;
	}
	$paths = array();
	$total = 0;
	foreach ( pediment_form_submission_files( $submission_id ) as $list ) {
		foreach ( $list as $file ) {
			$paths[] = $file['abs'];
			$total  += (int) filesize( $file['abs'] );
		}
	}
	if ( array() === $paths ) {
		return $none;
	}

	$link  = admin_url( 'post.php?post=' . $submission_id . '&action=edit' );
	$limit = (int) apply_filters( 'pediment_form_mail_attachment_limit', 10 * MB_IN_BYTES, $dest, $submission_id );
	if ( ! pediment_form_bool( $dest['attach_files'] ?? true ) ) {
		/* translators: %s: wp-admin URL of the stored submission. */
		$note = sprintf( __( 'Uploaded files are not attached. Download them here: %s', 'pediment' ), $link );
	} elseif ( $total > $limit ) {
		/* translators: 1: size limit, e.g. "10 MB"; 2: wp-admin URL of the stored submission. */
		$note = sprintf( __( 'The uploaded files are larger than %1$s and are not attached. Download them here: %2$s', 'pediment' ), pediment_form_file_size_label( $limit ), $link );
	} else {
		return array(
			'paths' => $paths,
			'note'  => '',
		);
	}
	return array(
		'paths' => array(),
		'note'  => $note,
	);
}

/**
 * Read a boolean the way a destination record stores it: true, false, 1, 0,
 * "1", "0", "true", "false", "on", "off", "yes", "no".
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function pediment_form_bool( $value ): bool {
	return is_bool( $value ) ? $value : (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}
