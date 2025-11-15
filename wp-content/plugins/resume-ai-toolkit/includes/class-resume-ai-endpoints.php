<?php
/**
 * REST API endpoints for the Resume AI Toolkit.
 */

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Smalot\PdfParser\Parser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Resume_AI_EndPoints' ) ) {

    class Resume_AI_EndPoints {

        const REST_NAMESPACE = 'resume-ai/v1';
        const CACHE_TTL      = 30 * MINUTE_IN_SECONDS;
        const MAX_FILE_SIZE  = 5 * 1048576; // 5MB
        const MODEL          = 'gpt-4o-mini';
        const SUPPORTED_EXT  = [ 'pdf', 'doc', 'docx' ];

        /**
         * Bootstrap hooks.
         */
        public function __construct() {
            add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        }

        /**
         * Register REST routes.
         */
        public function register_routes() {
            register_rest_route(
                self::REST_NAMESPACE,
                '/optimize',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'optimize_resume' ],
                    'permission_callback' => '__return_true',
                ]
            );

            register_rest_route(
                self::REST_NAMESPACE,
                '/builder',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'builder_optimize' ],
                    'permission_callback' => '__return_true',
                ]
            );

            register_rest_route(
                self::REST_NAMESPACE,
                '/export',
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'export_resume' ],
                    'permission_callback' => [ $this, 'export_permission' ],
                ]
            );
        }

        /**
         * Handle resume optimization requests coming from the Upload form.
         */
        public function optimize_resume( WP_REST_Request $request ) {
            $file = $this->prepare_uploaded_file( 'resume_file' );
            if ( is_wp_error( $file ) ) {
                return $this->error_response( $file->get_error_message(), 400 );
            }

            $resume_text = $this->extract_text_from_file( $file['path'], $file['ext'] );
            if ( is_wp_error( $resume_text ) ) {
                return $this->error_response( $resume_text->get_error_message(), 400 );
            }

            if ( empty( $resume_text ) ) {
                $fallback_text = $this->extract_text_with_cli_strings( $file['path'] );
                if ( ! empty( $fallback_text ) ) {
                    $resume_text = $fallback_text;
                }
            }

            if ( empty( $resume_text ) ) {
                return $this->error_response( __( 'We could not read text from the uploaded file. Please try another format.', 'resume-ai-toolkit' ), 400 );
            }

            $target_role = sanitize_textarea_field( $request->get_param( 'target_role' ) ?? '' );
            $priority    = $this->sanitize_priority( $request->get_param( 'priority' ) ?? '' );
            $user_email  = sanitize_email( $request->get_param( 'user_email' ) ?? '' );

            if ( ! $this->is_live_mode() ) {
                $analysis                      = $this->build_mock_analysis( $resume_text, $target_role );
                $analysis['resume_document']   = $this->build_suggestions_document( $analysis );
                $this->maybe_log_optimize_submission(
                    $analysis,
                    [
                        'email'       => $user_email,
                        'file_name'   => $file['name'],
                        'target_role' => $target_role,
                        'priority'    => $priority,
                    ]
                );

                return $this->success_response( $analysis, __( 'Dry run: generated mock suggestions.', 'resume-ai-toolkit' ) );
            }

            $cache_key = $this->build_cache_key( 'optimize', sha1( $file['hash'] . '|' . $target_role ) );
            $cached    = $this->get_cached_response( $cache_key );
            $analysis  = null;
            $message   = '';

            if ( $cached ) {
                $analysis = $cached;
                $message  = __( 'Resume analyzed successfully (cached).', 'resume-ai-toolkit' );
            } else {
                $analysis = $this->request_resume_analysis( $resume_text, $target_role );
                if ( is_wp_error( $analysis ) ) {
                    return $this->error_response( $analysis->get_error_message(), 500 );
                }

                $this->set_cached_response( $cache_key, $analysis );
                $message = __( 'Resume analyzed successfully.', 'resume-ai-toolkit' );
            }

            $this->maybe_log_optimize_submission(
                $analysis,
                [
                    'email'       => $user_email,
                    'file_name'   => $file['name'],
                    'target_role' => $target_role,
                    'priority'    => $priority,
                ]
            );

            return $this->success_response( $analysis, $message );
        }

        /**
         * Handle builder optimization requests.
         */
        public function builder_optimize( WP_REST_Request $request ) {
            $payload = $request->get_json_params();
            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return $this->error_response( __( 'Invalid resume payload.', 'resume-ai-toolkit' ), 400 );
            }

            $mode = isset( $payload['ai_mode'] ) ? sanitize_key( $payload['ai_mode'] ) : '';
            if ( 'bullet' === $mode ) {
                return $this->builder_rewrite_bullet( $payload );
            }

            $resume = $this->sanitize_builder_payload( $payload );

            if ( empty( $resume['first_name'] ) || empty( $resume['last_name'] ) ) {
                return $this->error_response( __( 'Please provide at least a first and last name.', 'resume-ai-toolkit' ), 400 );
            }

            if ( ! $this->is_live_mode() ) {
                $mock = $this->build_mock_builder_payload( $resume );
                $this->maybe_log_builder_submission( $resume, $mock );
                return $this->success_response( $mock, __( 'Dry run: generated mock builder output.', 'resume-ai-toolkit' ) );
            }

            $signature = sha1( wp_json_encode( $resume ) );
            $cache_key = $this->build_cache_key( 'builder', $signature );
            $cached    = $this->get_cached_response( $cache_key );
            $payload   = null;
            $message   = '';

            if ( $cached ) {
                $payload = $cached;
                $message = __( 'Resume generated successfully (cached).', 'resume-ai-toolkit' );
            } else {
                $payload = $this->request_builder_document( $resume );
                if ( is_wp_error( $payload ) ) {
                    return $this->error_response( $payload->get_error_message(), 500 );
                }

                $this->set_cached_response( $cache_key, $payload );
                $message = __( 'Resume generated successfully.', 'resume-ai-toolkit' );
            }

            $this->maybe_log_builder_submission( $resume, $payload );

            return $this->success_response( $payload, $message );
        }

        /**
         * Export resume data as a PDF or DOCX file.
         */
        public function export_resume( WP_REST_Request $request ) {
            if ( ! $this->export_permission() ) {
                return $this->error_response( __( 'An active subscription is required to export resumes.', 'resume-ai-toolkit' ), 403 );
            }

            $payload = $request->get_json_params();
            if ( empty( $payload['data'] ) || ! is_array( $payload['data'] ) ) {
                return $this->error_response( __( 'Resume payload is missing.', 'resume-ai-toolkit' ), 400 );
            }

            $format = isset( $payload['format'] ) ? strtolower( sanitize_key( $payload['format'] ) ) : 'pdf';
            if ( ! in_array( $format, [ 'pdf', 'docx' ], true ) ) {
                return $this->error_response( __( 'Unsupported export format.', 'resume-ai-toolkit' ), 400 );
            }

            $type     = isset( $payload['type'] ) ? sanitize_key( $payload['type'] ) : 'builder';
            $filename = ! empty( $payload['filename'] ) ? sanitize_file_name( $payload['filename'] ) : '';
            $filename = $this->normalize_export_filename( $filename, $format, $type );

            if ( 'enhance' === $type ) {
                $analysis = $this->sanitize_enhance_export_payload( $payload['data'] );
                if ( is_wp_error( $analysis ) ) {
                    return $this->error_response( $analysis->get_error_message(), 400 );
                }

                if ( 'docx' === $format ) {
                    $docx = $this->build_enhance_docx( $analysis );
                    if ( is_wp_error( $docx ) ) {
                        return $this->error_response( $docx->get_error_message(), 500 );
                    }

                    return $this->file_response( $docx, $filename, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
                }

                $html = $this->render_enhance_template( $analysis );
                if ( is_wp_error( $html ) ) {
                    return $this->error_response( $html->get_error_message(), 500 );
                }

                $pdf = $this->render_pdf_binary( $html );
                if ( is_wp_error( $pdf ) ) {
                    return $this->error_response( $pdf->get_error_message(), 500 );
                }

                return $this->file_response( $pdf, $filename, 'application/pdf' );
            }

            $resume = $this->sanitize_builder_payload( $payload['data'] );

            if ( 'docx' === $format ) {
                $docx = $this->build_builder_docx( $resume );
                if ( is_wp_error( $docx ) ) {
                    return $this->error_response( $docx->get_error_message(), 500 );
                }

                return $this->file_response( $docx, $filename, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
            }

            $html = $this->render_pdf_template( $resume );
            if ( is_wp_error( $html ) ) {
                return $this->error_response( $html->get_error_message(), 500 );
            }

            $pdf = $this->render_pdf_binary( $html );
            if ( is_wp_error( $pdf ) ) {
                return $this->error_response( $pdf->get_error_message(), 500 );
            }

            return $this->file_response( $pdf, $filename, 'application/pdf' );
        }

        /**
         * Prepare uploaded file metadata.
         */
        private function prepare_uploaded_file( string $field ) {
            if ( empty( $_FILES[ $field ] ) ) {
                return new WP_Error( 'resume_ai_missing_file', __( 'Please attach a resume before submitting.', 'resume-ai-toolkit' ) );
            }

            $file = $_FILES[ $field ];

            if ( ! empty( $file['error'] ) ) {
                return new WP_Error( 'resume_ai_upload_error', wp_get_upload_error( $file['error'] ) );
            }

            if ( (int) $file['size'] > self::MAX_FILE_SIZE ) {
                return new WP_Error( 'resume_ai_file_size', __( 'Files must be smaller than 5MB.', 'resume-ai-toolkit' ) );
            }

            $type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
            $ext  = strtolower( $type['ext'] ?? pathinfo( $file['name'], PATHINFO_EXTENSION ) );

            if ( ! in_array( $ext, self::SUPPORTED_EXT, true ) ) {
                return new WP_Error( 'resume_ai_file_type', __( 'Please upload a PDF, DOC, or DOCX file.', 'resume-ai-toolkit' ) );
            }

            return [
                'path' => $file['tmp_name'],
                'ext'  => $ext,
                'name' => sanitize_file_name( $file['name'] ),
                'hash' => sha1_file( $file['tmp_name'] ),
            ];
        }

        /**
         * Extract plain text from a resume file.
         */
        private function extract_text_from_file( string $path, string $extension ) {
            switch ( $extension ) {
                case 'pdf':
                    return $this->extract_pdf_text( $path );
                case 'docx':
                    return $this->extract_docx_text( $path );
                case 'doc':
                    return $this->extract_doc_text( $path );
                default:
                    return new WP_Error( 'resume_ai_unsupported', __( 'Unsupported file format.', 'resume-ai-toolkit' ) );
            }
        }

        /**
         * Extract text from PDF via smalot/pdfparser.
         */
        private function extract_pdf_text( string $path ) {
            if ( ! class_exists( Parser::class ) ) {
                return new WP_Error( 'resume_ai_pdf_parser_missing', __( 'PDF parser library is not available.', 'resume-ai-toolkit' ) );
            }

            try {
                $parser = new Parser();
                $pdf    = $parser->parseFile( $path );
                $text   = $pdf->getText();
                return $this->normalize_resume_text( $text );
            } catch ( \Exception $exception ) {
                error_log( sprintf( 'Resume AI Toolkit: PDF parse failed - %s', $exception->getMessage() ) );
                return new WP_Error( 'resume_ai_pdf_parse_failed', __( 'Unable to read the PDF contents.', 'resume-ai-toolkit' ) );
            }
        }

        /**
         * Extract text from DOCX by reading the document XML.
         */
        private function extract_docx_text( string $path ) {
            $zip = new ZipArchive();
            if ( true === $zip->open( $path ) ) {
                $xml = $zip->getFromName( 'word/document.xml' );
                $zip->close();

                if ( false !== $xml ) {
                    $text = $this->normalize_resume_text( wp_strip_all_tags( $xml ) );
                    if ( ! empty( $text ) ) {
                        return $text;
                    }
                }
            }

            return $this->extract_docx_with_phpword( $path );
        }

        /**
         * Extract text from legacy DOC files using PhpWord when available.
         */
        private function extract_doc_text( string $path ) {
            if ( ! class_exists( '\\PhpOffice\\PhpWord\\IOFactory' ) ) {
                return new WP_Error( 'resume_ai_doc_parser_missing', __( 'DOC parsing library is not available. Please upload a PDF or DOCX file instead.', 'resume-ai-toolkit' ) );
            }

            try {
                $document = \PhpOffice\PhpWord\IOFactory::load( $path );
                $text     = '';

                foreach ( $document->getSections() as $section ) {
                    foreach ( $section->getElements() as $element ) {
                        $text .= $this->walk_phpword_element( $element );
                    }
                }

                return $this->normalize_resume_text( $text );
            } catch ( \Exception $exception ) {
                error_log( sprintf( 'Resume AI Toolkit: DOC parse failed - %s', $exception->getMessage() ) );
                return new WP_Error( 'resume_ai_doc_parse_failed', __( 'Unable to read the DOC contents. Try converting it to PDF.', 'resume-ai-toolkit' ) );
            }
        }

        /**
         * Recursively gather text from PhpWord elements.
         */
        private function walk_phpword_element( $element ) {
            $text = '';

            if ( method_exists( $element, 'getText' ) ) {
                $text .= $element->getText() . "\n";
            }

            if ( method_exists( $element, 'getElements' ) ) {
                foreach ( $element->getElements() as $child ) {
                    $text .= $this->walk_phpword_element( $child );
                }
            }

            if ( method_exists( $element, 'getRows' ) ) {
                foreach ( $element->getRows() as $row ) {
                    foreach ( $row->getCells() as $cell ) {
                        foreach ( $cell->getElements() as $child ) {
                            $text .= $this->walk_phpword_element( $child );
                        }
                    }
                }
            }

            return $text;
        }

        /**
         * Normalize resume text before sending to OpenAI.
         */
        private function normalize_resume_text( string $text ) {
            $clean = preg_replace( '/[\t\r]+/', ' ', $text );
            $clean = preg_replace( '/\s{2,}/', ' ', $clean );
            return trim( $clean );
        }

        private function extract_docx_with_phpword( string $path ) {
            if ( ! class_exists( '\\PhpOffice\\PhpWord\\IOFactory' ) ) {
                return new WP_Error( 'resume_ai_docx_parser_missing', __( 'DOCX parsing library is not available. Please upload a PDF instead.', 'resume-ai-toolkit' ) );
            }

            try {
                $document = \PhpOffice\PhpWord\IOFactory::load( $path );
                $text     = '';

                foreach ( $document->getSections() as $section ) {
                    foreach ( $section->getElements() as $element ) {
                        $text .= $this->walk_phpword_element( $element );
                    }
                }

                $text = $this->normalize_resume_text( $text );

                if ( empty( $text ) ) {
                    return new WP_Error( 'resume_ai_docx_empty', __( 'Unable to read the DOCX contents.', 'resume-ai-toolkit' ) );
                }

                return $text;
            } catch ( \Exception $exception ) {
                error_log( sprintf( 'Resume AI Toolkit: DOCX fallback parse failed - %s', $exception->getMessage() ) );
                return new WP_Error( 'resume_ai_docx_parse_failed', __( 'Unable to read the DOCX contents. Try converting it to PDF.', 'resume-ai-toolkit' ) );
            }
        }

        /**
         * Attempt to extract text using the system `strings` utility as a fallback.
         */
        private function extract_text_with_cli_strings( string $path ) {
            if ( ! function_exists( 'shell_exec' ) ) {
                return '';
            }

            $binary = trim( (string) @shell_exec( 'which strings' ) );
            if ( empty( $binary ) ) {
                return '';
            }

            $output = @shell_exec( escapeshellcmd( $binary ) . ' ' . escapeshellarg( $path ) );
            if ( empty( $output ) ) {
                return '';
            }

            return $this->normalize_resume_text( $output );
        }

        /**
         * Request resume analysis from OpenAI.
         */
        private function request_resume_analysis( string $resume_text, string $target_role ) {
            $prompt = sprintf(
                "Resume text:\n<<<RESUME>>>\n%s\n<<<END>>>\nTarget role / keywords: %s",
                mb_substr( $resume_text, 0, 20000, 'UTF-8' ),
                $target_role ? $target_role : 'Not specified'
            );

            $body = [
                'model'            => self::MODEL,
                'temperature'      => 0.2,
                'max_output_tokens'=> 900,
                'messages'         => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a professional resume optimizer. Respond with actionable insights only.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format'  => [
                    'type'        => 'json_schema',
                    'json_schema' => [
                        'name'   => 'resume_analysis',
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'grammar'    => [ 'type' => 'string' ],
                                'keywords'   => [ 'type' => 'string' ],
                                'formatting' => [ 'type' => 'string' ],
                                'summary'    => [ 'type' => 'string' ],
                                'score'      => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 100 ],
                            ],
                            'required'   => [ 'grammar', 'keywords', 'formatting', 'score' ],
                        ],
                    ],
                ],
            ];

            $response = $this->call_openai( $body );
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $content = trim( $response['choices'][0]['message']['content'] ?? '' );
            $data    = json_decode( $content, true );

            if ( JSON_ERROR_NONE !== json_last_error() ) {
                return new WP_Error( 'resume_ai_bad_payload', __( 'The AI response could not be parsed.', 'resume-ai-toolkit' ) );
            }

            $score = isset( $data['score'] ) ? max( 0, min( 100, (int) $data['score'] ) ) : null;

            return [
                'grammar'         => sanitize_textarea_field( $data['grammar'] ?? '' ),
                'keywords'        => sanitize_textarea_field( $data['keywords'] ?? '' ),
                'formatting'      => sanitize_textarea_field( $data['formatting'] ?? '' ),
                'summary'         => sanitize_textarea_field( $data['summary'] ?? '' ),
                'score'           => $score,
                'resume_document' => $this->build_suggestions_document( $data ),
            ];
        }

        /**
         * Request builder output from OpenAI.
         */
        private function request_builder_document( array $resume ) {
            $plain = $this->build_plaintext_resume( $resume );

            $body = [
                'model'            => self::MODEL,
                'temperature'      => 0.25,
                'max_output_tokens'=> 1200,
                'messages'         => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a senior resume writer. Rewrite content using confident, metric-driven bullets. Preserve truthful facts and return JSON matching the schema.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => sprintf(
                            'Candidate profile (JSON): %s\nPlain-text resume:\n%s',
                            wp_json_encode( $resume ),
                            $plain
                        ),
                    ],
                ],
                'response_format'  => [
                    'type'        => 'json_schema',
                    'json_schema' => [
                        'name'   => 'resume_builder',
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'summary'   => [ 'type' => 'string' ],
                                'employment'=> [
                                    'type'  => 'array',
                                    'items' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'title'   => [ 'type' => 'string' ],
                                            'company' => [ 'type' => 'string' ],
                                            'start'   => [ 'type' => 'string' ],
                                            'end'     => [ 'type' => 'string' ],
                                            'summary' => [ 'type' => 'string' ],
                                        ],
                                        'required'   => [ 'title', 'company', 'summary' ],
                                    ],
                                ],
                                'education' => [
                                    'type'  => 'array',
                                    'items' => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'school' => [ 'type' => 'string' ],
                                            'degree' => [ 'type' => 'string' ],
                                            'start'  => [ 'type' => 'string' ],
                                            'end'    => [ 'type' => 'string' ],
                                        ],
                                        'required'   => [ 'school', 'degree' ],
                                    ],
                                ],
                                'skills'    => [
                                    'type'  => 'array',
                                    'items' => [ 'type' => 'string' ],
                                ],
                                'score'     => [ 'type' => 'integer', 'minimum' => 0, 'maximum' => 100 ],
                            ],
                            'required'   => [ 'summary', 'employment', 'education', 'skills', 'score' ],
                        ],
                    ],
                ],
            ];

            $response = $this->call_openai( $body );
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $content = trim( $response['choices'][0]['message']['content'] ?? '' );
            $data    = json_decode( $content, true );

            if ( JSON_ERROR_NONE !== json_last_error() ) {
                return new WP_Error( 'resume_ai_builder_payload', __( 'The AI response could not be parsed.', 'resume-ai-toolkit' ) );
            }

            $sections = [
                'summary'    => sanitize_textarea_field( $data['summary'] ?? '' ),
                'employment' => $this->normalize_employment_entries( $data['employment'] ?? [] ),
                'education'  => $this->normalize_education_entries( $data['education'] ?? [] ),
                'skills'     => $this->sanitize_skills_array( $data['skills'] ?? [] ),
            ];

            $payload = [
                'score'           => isset( $data['score'] ) ? max( 0, min( 100, (int) $data['score'] ) ) : null,
                'sections'        => $sections,
                'resume_document' => $this->build_resume_document( $resume, $sections ),
            ];

            return $payload;
        }

        /**
         * Request a rewritten bullet from OpenAI.
         */
        private function request_bullet_rewrite( array $resume, array $target ) {
            $skills = $resume['skills'] ?? '';
            if ( is_array( $skills ) ) {
                $skills = implode( ', ', $skills );
            }

            $role_line = trim(
                sprintf(
                    '%s %s',
                    $target['title'] ?: ( $resume['job_title'] ?? '' ),
                    $target['company'] ? 'at ' . $target['company'] : ''
                )
            );

            $prompt = sprintf(
                "Candidate summary: %s\nTarget role: %s\nKey skills: %s\nExisting bullet:\n%s\nRewrite this bullet as one concise sentence that leads with a strong verb, quantifies impact, and highlights business results. Avoid using personal pronouns.",
                $resume['summary'] ?? 'Not provided',
                $role_line ?: ( $resume['job_title'] ?? 'Not specified' ),
                $skills ?: 'Not provided',
                $target['summary']
            );

            $body = [
                'model'            => self::MODEL,
                'temperature'      => 0.35,
                'max_output_tokens'=> 220,
                'messages'         => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an expert resume editor. Return JSON containing a single rewritten bullet optimized for clarity, metrics, and ATS scanning.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format'  => [
                    'type'        => 'json_schema',
                    'json_schema' => [
                        'name'   => 'resume_bullet',
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'bullet' => [ 'type' => 'string' ],
                            ],
                            'required'   => [ 'bullet' ],
                        ],
                    ],
                ],
            ];

            $response = $this->call_openai( $body );
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $content = trim( $response['choices'][0]['message']['content'] ?? '' );
            $data    = json_decode( $content, true );

            if ( JSON_ERROR_NONE !== json_last_error() ) {
                return new WP_Error( 'resume_ai_bad_payload', __( 'The AI response could not be parsed.', 'resume-ai-toolkit' ) );
            }

            $bullet = sanitize_textarea_field( $data['bullet'] ?? '' );
            if ( '' === $bullet ) {
                return new WP_Error( 'resume_ai_missing_bullet', __( 'The AI service did not return a rewritten bullet.', 'resume-ai-toolkit' ) );
            }

            return [
                'bullet' => $bullet,
                'index'  => $target['index'],
            ];
        }

        /**
         * Normalize employment entries.
         */
        private function normalize_employment_entries( $entries ) {
            if ( ! is_array( $entries ) ) {
                return [];
            }

            $normalized = [];
            foreach ( $entries as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }

                $entry = [
                    'title'   => sanitize_text_field( $entry['title'] ?? '' ),
                    'company' => sanitize_text_field( $entry['company'] ?? '' ),
                    'start'   => sanitize_text_field( $entry['start'] ?? '' ),
                    'end'     => sanitize_text_field( $entry['end'] ?? '' ),
                    'summary' => sanitize_textarea_field( $entry['summary'] ?? '' ),
                ];

                if ( '' === implode( '', $entry ) ) {
                    continue;
                }

                $normalized[] = $entry;
            }

            return $normalized;
        }

        /**
         * Normalize education entries.
         */
        private function normalize_education_entries( $entries ) {
            if ( ! is_array( $entries ) ) {
                return [];
            }

            $normalized = [];
            foreach ( $entries as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }

                $entry = [
                    'school' => sanitize_text_field( $entry['school'] ?? '' ),
                    'degree' => sanitize_text_field( $entry['degree'] ?? '' ),
                    'start'  => sanitize_text_field( $entry['start'] ?? '' ),
                    'end'    => sanitize_text_field( $entry['end'] ?? '' ),
                ];

                if ( '' === implode( '', $entry ) ) {
                    continue;
                }

                $normalized[] = $entry;
            }

            return $normalized;
        }

        /**
         * Sanitize skills array.
         */
        private function sanitize_skills_array( $skills ) {
            if ( is_string( $skills ) ) {
                $skills = explode( ',', $skills );
            }

            if ( ! is_array( $skills ) ) {
                return [];
            }

            $clean = [];
            foreach ( $skills as $skill ) {
                $skill = sanitize_text_field( $skill );
                if ( $skill ) {
                    $clean[] = $skill;
                }
            }

            return array_values( array_unique( $clean ) );
        }

        /**
         * Sanitize builder payload from the frontend.
         */
        private function sanitize_builder_payload( array $payload ) {
            $clean = [];
            $fields = [ 'job_title', 'location', 'first_name', 'last_name', 'email', 'phone', 'summary', 'skills' ];

            foreach ( $fields as $field ) {
                $value = $payload[ $field ] ?? '';

                if ( 'email' === $field ) {
                    $clean[ $field ] = sanitize_email( $value );
                } elseif ( in_array( $field, [ 'summary', 'skills' ], true ) ) {
                    $clean[ $field ] = sanitize_textarea_field( $value );
                } else {
                    $clean[ $field ] = sanitize_text_field( $value );
                }
            }

            $clean['employment'] = [];
            if ( ! empty( $payload['employment'] ) && is_array( $payload['employment'] ) ) {
                foreach ( $payload['employment'] as $role ) {
                    if ( ! is_array( $role ) ) {
                        continue;
                    }
                    $entry = [
                        'title'   => sanitize_text_field( $role['title'] ?? '' ),
                        'company' => sanitize_text_field( $role['company'] ?? '' ),
                        'start'   => sanitize_text_field( $role['start'] ?? '' ),
                        'end'     => sanitize_text_field( $role['end'] ?? '' ),
                        'summary' => sanitize_textarea_field( $role['summary'] ?? '' ),
                    ];

                    if ( implode( '', $entry ) !== '' ) {
                        $clean['employment'][] = $entry;
                    }
                }
            }

            $clean['education'] = [];
            if ( ! empty( $payload['education'] ) && is_array( $payload['education'] ) ) {
                foreach ( $payload['education'] as $edu ) {
                    if ( ! is_array( $edu ) ) {
                        continue;
                    }
                    $entry = [
                        'school' => sanitize_text_field( $edu['school'] ?? '' ),
                        'degree' => sanitize_text_field( $edu['degree'] ?? '' ),
                        'start'  => sanitize_text_field( $edu['start'] ?? '' ),
                        'end'    => sanitize_text_field( $edu['end'] ?? '' ),
                    ];

                    if ( implode( '', $entry ) !== '' ) {
                        $clean['education'][] = $entry;
                    }
                }
            }

            return $clean;
        }

        /**
         * Handle targeted bullet rewrites for the builder flow.
         */
        private function builder_rewrite_bullet( array $payload ) {
            $resume = $this->sanitize_builder_payload( $payload );
            $target = $this->sanitize_target_bullet( $payload['target_bullet'] ?? [] );
            if ( is_wp_error( $target ) ) {
                return $this->error_response( $target->get_error_message(), 400 );
            }

            $index = $target['index'];
            if ( isset( $resume['employment'][ $index ] ) ) {
                $role = $resume['employment'][ $index ];
                if ( empty( $target['title'] ) && ! empty( $role['title'] ) ) {
                    $target['title'] = $role['title'];
                }
                if ( empty( $target['company'] ) && ! empty( $role['company'] ) ) {
                    $target['company'] = $role['company'];
                }
            }

            if ( ! $this->is_live_mode() ) {
                $mock = sprintf(
                    __( 'Dry run rewrite: %s — highlight metrics, tools, and ownership.', 'resume-ai-toolkit' ),
                    $target['summary']
                );

                return $this->success_response(
                    [
                        'bullet' => $mock,
                        'index'  => $index,
                    ],
                    __( 'Dry run bullet generated.', 'resume-ai-toolkit' )
                );
            }

            $result = $this->request_bullet_rewrite( $resume, $target );
            if ( is_wp_error( $result ) ) {
                return $this->error_response( $result->get_error_message(), 500 );
            }

            return $this->success_response( $result, __( 'Bullet rewritten successfully.', 'resume-ai-toolkit' ) );
        }

        /**
         * Sanitize the bullet rewrite payload.
         */
        private function sanitize_target_bullet( $target ) {
            if ( ! is_array( $target ) ) {
                return new WP_Error( 'resume_ai_missing_bullet', __( 'Bullet data is missing.', 'resume-ai-toolkit' ) );
            }

            $index   = isset( $target['index'] ) ? max( 0, (int) $target['index'] ) : 0;
            $summary = sanitize_textarea_field( $target['summary'] ?? '' );
            if ( '' === $summary ) {
                return new WP_Error( 'resume_ai_missing_bullet', __( 'Provide a bullet to rewrite.', 'resume-ai-toolkit' ) );
            }

            return [
                'index'   => $index,
                'summary' => $summary,
                'title'   => sanitize_text_field( $target['title'] ?? '' ),
                'company' => sanitize_text_field( $target['company'] ?? '' ),
            ];
        }

        /**
         * Sanitize enhance export payloads before rendering files.
         */
        private function sanitize_enhance_export_payload( array $payload ) {
            $raw_document = isset( $payload['resume_document'] ) ? (string) $payload['resume_document'] : '';
            $sanitized    = wp_kses_post( $raw_document );

            if ( '' === trim( wp_strip_all_tags( $sanitized ) ) ) {
                return new WP_Error( 'resume_ai_missing_preview', __( 'Optimized resume preview is missing.', 'resume-ai-toolkit' ) );
            }

            return [
                'resume_document' => $sanitized,
                'score'           => isset( $payload['score'] ) ? max( 0, min( 100, (int) $payload['score'] ) ) : null,
                'grammar'         => sanitize_textarea_field( $payload['grammar'] ?? '' ),
                'keywords'        => sanitize_textarea_field( $payload['keywords'] ?? '' ),
                'formatting'      => sanitize_textarea_field( $payload['formatting'] ?? '' ),
                'summary'         => sanitize_textarea_field( $payload['summary'] ?? '' ),
            ];
        }

        /**
         * Sanitize the priority selector from the upload form.
         */
        private function sanitize_priority( $value ) {
            $priority = sanitize_key( $value );
            $allowed  = [ 'impact', 'story', 'keywords' ];
            return in_array( $priority, $allowed, true ) ? $priority : '';
        }

        /**
         * Build plain text resume for OpenAI context.
         */
        private function build_plaintext_resume( array $resume ) {
            $lines = [];
            $name  = trim( $resume['first_name'] . ' ' . $resume['last_name'] );
            if ( $name ) {
                $lines[] = $name;
            }
            if ( ! empty( $resume['job_title'] ) ) {
                $lines[] = $resume['job_title'];
            }
            $contact = array_filter( [ $resume['location'] ?? '', $resume['email'] ?? '', $resume['phone'] ?? '' ] );
            if ( $contact ) {
                $lines[] = implode( ' • ', $contact );
            }
            $lines[] = '';

            if ( ! empty( $resume['summary'] ) ) {
                $lines[] = 'SUMMARY';
                $lines[] = $resume['summary'];
                $lines[] = '';
            }

            if ( ! empty( $resume['employment'] ) ) {
                $lines[] = 'EXPERIENCE';
                foreach ( $resume['employment'] as $role ) {
                    $lines[] = trim( sprintf( '%s • %s', $role['title'] ?? '', $role['company'] ?? '' ), ' •' );
                    $lines[] = trim( sprintf( '%s – %s', $role['start'] ?? '', $role['end'] ?? '' ), ' –' );
                    $lines[] = $role['summary'] ?? '';
                    $lines[] = '';
                }
            }

            if ( ! empty( $resume['education'] ) ) {
                $lines[] = 'EDUCATION';
                foreach ( $resume['education'] as $edu ) {
                    $lines[] = trim( sprintf( '%s, %s', $edu['degree'] ?? '', $edu['school'] ?? '' ), ', ' );
                    $lines[] = trim( sprintf( '%s – %s', $edu['start'] ?? '', $edu['end'] ?? '' ), ' –' );
                    $lines[] = '';
                }
            }

            if ( ! empty( $resume['skills'] ) ) {
                $lines[] = 'SKILLS';
                $lines[] = is_array( $resume['skills'] ) ? implode( ', ', $resume['skills'] ) : $resume['skills'];
            }

            return trim( implode( "\n", $lines ) );
        }

        /**
         * Build the finalized resume document string.
         */
        private function build_resume_document( array $profile, array $sections ) {
            $snapshot = [
                'first_name' => $profile['first_name'] ?? '',
                'last_name'  => $profile['last_name'] ?? '',
                'job_title'  => $profile['job_title'] ?? '',
                'location'   => $profile['location'] ?? '',
                'email'      => $profile['email'] ?? '',
                'phone'      => $profile['phone'] ?? '',
                'summary'    => $sections['summary'] ?? '',
                'skills'     => $sections['skills'] ?? [],
                'employment' => $sections['employment'] ?? [],
                'education'  => $sections['education'] ?? [],
            ];

            return $this->build_plaintext_resume( $snapshot );
        }

        /**
         * Build the suggestion document for the upload flow.
         */
        private function build_suggestions_document( array $data ) {
            $lines = [
                'Optimized Resume Suggestions',
                '',
                sprintf( 'AI Score: %s/100', isset( $data['score'] ) ? (int) $data['score'] : '--' ),
                '',
                'Grammar & Clarity',
                $data['grammar'] ?? '',
                '',
                'Keywords (ATS)',
                $data['keywords'] ?? '',
                '',
                'Formatting & Structure',
                $data['formatting'] ?? '',
            ];

            if ( ! empty( $data['summary'] ) ) {
                $lines[] = '';
                $lines[] = 'Executive Summary';
                $lines[] = $data['summary'];
            }

            return trim( implode( "\n", $lines ) );
        }

        /**
         * Snapshot of the current logged-in user.
         */
		private function get_current_user_profile() {
			if ( ! is_user_logged_in() ) {
				return [
					'id'         => 0,
					'first_name' => '',
					'last_name'  => '',
					'email'      => '',
				];
			}

			$user = wp_get_current_user();

			return [
				'id'         => isset( $user->ID ) ? (int) $user->ID : 0,
				'first_name' => $user->first_name ?? '',
				'last_name'  => $user->last_name ?? '',
				'email'      => $user->user_email ?? '',
			];
		}

		/**
		 * Resolve a user ID from either the current session or email address.
		 */
		private function maybe_resolve_user_id( string $email, int $user_id = 0 ) {
			$user_id = absint( $user_id );
			if ( $user_id > 0 ) {
				return $user_id;
			}

			if ( $email ) {
				$user = get_user_by( 'email', $email );
				if ( $user ) {
					return (int) $user->ID;
				}
			}

			return 0;
		}

        /**
         * Whether outbound OpenAI calls are enabled.
         */
        private function is_live_mode() {
            $option = get_option( 'resume_ai_live_mode', null );
            if ( null !== $option ) {
                return (bool) $option;
            }

            if ( defined( 'RESUME_AI_LIVE_MODE' ) ) {
                return (bool) RESUME_AI_LIVE_MODE;
            }

            return true;
        }

        /**
         * Build a deterministic mock analysis payload for dry-run mode.
         */
        private function build_mock_analysis( string $resume_text, string $target_role ) {
            $plain_text = wp_strip_all_tags( $resume_text );
            $word_count = max( 1, str_word_count( $plain_text ) );
            $role_label = $target_role ? $target_role : __( 'general roles', 'resume-ai-toolkit' );
            $excerpt    = $plain_text ? wp_trim_words( $plain_text, 30, '…' ) : __( 'Add more accomplishments to unlock richer suggestions.', 'resume-ai-toolkit' );
            $hash_seed  = hexdec( substr( md5( $plain_text . $target_role ), 0, 2 ) );
            $score      = 65 + ( $hash_seed % 25 );

            return [
                'grammar'    => sprintf(
                    __( 'Dry run: reviewed roughly %1$d words for %2$s. Lead each bullet with a strong verb and trim filler language.', 'resume-ai-toolkit' ),
                    $word_count,
                    $role_label
                ),
                'keywords'   => sprintf(
                    __( 'Prioritize ATS keywords tied to %s along with leadership, delivery, metrics, and stakeholder alignment.', 'resume-ai-toolkit' ),
                    $role_label
                ),
                'formatting' => __( 'Keep the file to 1–2 pages, align dates to the right edge, and limit each bullet to two lines for readability.', 'resume-ai-toolkit' ),
                'summary'    => sprintf(
                    __( 'Dry-run excerpt spotlight: %s', 'resume-ai-toolkit' ),
                    $excerpt
                ),
                'score'      => $score,
            ];
        }

        /**
         * Build a mock builder response for dry-run mode.
         */
        private function build_mock_builder_payload( array $resume ) {
            $role_label = $resume['job_title'] ?: __( 'your target role', 'resume-ai-toolkit' );
            $summary    = $resume['summary'] ?: sprintf(
                __( 'Dry run summary for %s. Replace this text with a 2–3 sentence story that highlights scope, tools, and measurable wins.', 'resume-ai-toolkit' ),
                $role_label
            );

            $employment = $resume['employment'];
            if ( empty( $employment ) ) {
                $employment = [
                    [
                        'title'   => $role_label ?: __( 'Sample Role', 'resume-ai-toolkit' ),
                        'company' => __( 'Dry Run Inc.', 'resume-ai-toolkit' ),
                        'start'   => 'Jan 2022',
                        'end'     => 'Present',
                        'summary' => __( 'Placeholder achievements. Swap in quantified bullets that show ownership, metrics, and collaboration.', 'resume-ai-toolkit' ),
                    ],
                ];
            }

            $education = $resume['education'];
            if ( empty( $education ) ) {
                $education = [
                    [
                        'school' => __( 'Sample University', 'resume-ai-toolkit' ),
                        'degree' => __( 'B.A. in Example Studies', 'resume-ai-toolkit' ),
                        'start'  => '2014',
                        'end'    => '2018',
                    ],
                ];
            }

            $skills = $resume['skills'];
            if ( is_string( $skills ) ) {
                $skills = array_filter( array_map( 'trim', preg_split( '/[,\\n]+/', $skills ) ) );
            }
            if ( empty( $skills ) || ! is_array( $skills ) ) {
                $skills = [ 'Leadership', 'Stakeholder Management', 'Process Optimization', 'Roadmap Planning' ];
            }

            $score_seed = hexdec( substr( md5( implode( '', [ $resume['first_name'], $resume['last_name'], $role_label ] ) ), 0, 2 ) );
            $score      = 70 + ( $score_seed % 25 );

            $sections = [
                'summary'    => $summary,
                'employment' => array_values( $employment ),
                'education'  => array_values( $education ),
                'skills'     => array_values( $skills ),
            ];

            return [
                'score'           => $score,
                'sections'        => $sections,
                'resume_document' => $this->build_resume_document( $resume, $sections ),
            ];
        }

        /**
         * Persist enhance-flow submissions when the data store is available.
         */
        private function maybe_log_optimize_submission( array $analysis, array $context ) {
            if ( ! class_exists( 'Resume_AI_Data_Store' ) || empty( $analysis ) ) {
                return;
            }

			$user       = $this->get_current_user_profile();
			$first_name = $context['first_name'] ?? $user['first_name'];
			$last_name  = $context['last_name'] ?? $user['last_name'];
			$email      = $context['email'] ?? $user['email'];
			$user_id    = $this->maybe_resolve_user_id( $email, $user['id'] ?? 0 );

			Resume_AI_Data_Store::log_submission(
				[
					'submission_type' => 'enhance',
					'user_id'         => $user_id,
					'first_name'      => $first_name,
					'last_name'       => $last_name,
					'email'           => $email,
                    'file_name'       => $context['file_name'] ?? '',
                    'target_role'     => $context['target_role'] ?? '',
                    'priority'        => $context['priority'] ?? '',
                    'score'           => $analysis['score'] ?? null,
                    'payload'         => [
                        'target_role' => $context['target_role'] ?? '',
                        'priority'    => $context['priority'] ?? '',
                    ],
                    'response'        => $analysis,
                    'document'        => $analysis['resume_document'] ?? '',
                ]
            );
        }

        /**
         * Persist builder submissions when the data store is available.
         */
        private function maybe_log_builder_submission( array $profile, array $result ) {
            if ( ! class_exists( 'Resume_AI_Data_Store' ) || empty( $result ) ) {
                return;
            }

			$user       = $this->get_current_user_profile();
			$first_name = $profile['first_name'] ?: $user['first_name'];
			$last_name  = $profile['last_name'] ?: $user['last_name'];
			$email      = $profile['email'] ?: $user['email'];
			$user_id    = $this->maybe_resolve_user_id( $email, $user['id'] ?? 0 );

			Resume_AI_Data_Store::log_submission(
				[
					'submission_type' => 'builder',
					'user_id'         => $user_id,
                    'first_name'      => $first_name,
                    'last_name'       => $last_name,
                    'email'           => $email,
                    'target_role'     => $profile['job_title'] ?? '',
                    'score'           => $result['score'] ?? null,
                    'payload'         => $profile,
                    'response'        => $result,
                    'document'        => $result['resume_document'] ?? '',
                ]
            );
        }

        /**
         * Render the PDF template.
         */
        private function render_pdf_template( array $resume ) {
            $path = plugin_dir_path( __FILE__ ) . '../views/export/resume-pdf-template.php';
            if ( ! file_exists( $path ) ) {
                return new WP_Error( 'resume_ai_template_missing', __( 'PDF template is missing.', 'resume-ai-toolkit' ) );
            }

            ob_start();
            $data = $resume;
            include $path;
            return ob_get_clean();
        }

        /**
         * Render the enhance flow export template.
         */
        private function render_enhance_template( array $analysis ) {
            $path = plugin_dir_path( __FILE__ ) . '../views/export/enhance-pdf-template.php';
            if ( ! file_exists( $path ) ) {
                return new WP_Error( 'resume_ai_template_missing', __( 'PDF template is missing.', 'resume-ai-toolkit' ) );
            }

            ob_start();
            $data = $analysis;
            include $path;
            return ob_get_clean();
        }

        /**
         * Convert an HTML string into a PDF binary blob.
         */
        private function render_pdf_binary( string $html ) {
            if ( ! class_exists( Dompdf::class ) ) {
                return new WP_Error( 'resume_ai_pdf_missing', __( 'PDF export dependencies are missing.', 'resume-ai-toolkit' ) );
            }

            $options = new Options();
            $options->set( 'isRemoteEnabled', true );

            $dompdf = new Dompdf( $options );
            $dompdf->loadHtml( $html, 'UTF-8' );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();

            return $dompdf->output();
        }

        /**
         * Build a standardized REST response with a base64 file payload.
         */
        private function file_response( string $binary, string $filename, string $mime_type ) {
            return $this->success_response(
                [
                    'filename'  => $filename,
                    'mime_type' => $mime_type,
                    'file'      => base64_encode( $binary ),
                ],
                __( 'Resume exported successfully.', 'resume-ai-toolkit' )
            );
        }

        /**
         * Ensure export filenames always include the correct extension.
         */
        private function normalize_export_filename( string $filename, string $format, string $type ) {
            $default = ( 'enhance' === $type ) ? 'enhanced-resume' : 'resume';
            $name    = $filename ?: sprintf( '%s.%s', $default, $format );
            $ext     = '.' . $format;

            if ( substr( $name, -strlen( $ext ) ) !== $ext ) {
                $name .= $ext;
            }

            return sanitize_file_name( $name );
        }

        /**
         * Build a DOCX export for the builder flow.
         */
        private function build_builder_docx( array $resume ) {
            if ( ! class_exists( PhpWord::class ) ) {
                return new WP_Error( 'resume_ai_docx_missing', __( 'DOCX export dependencies are missing.', 'resume-ai-toolkit' ) );
            }

            $php_word = new PhpWord();
            $php_word->setDefaultFontName( 'Inter' );
            $php_word->setDefaultFontSize( 11 );

            $section = $php_word->addSection(
                [
                    'marginLeft'   => 720,
                    'marginRight'  => 720,
                    'marginTop'    => 720,
                    'marginBottom' => 720,
                ]
            );

            $name = trim( ( $resume['first_name'] ?? '' ) . ' ' . ( $resume['last_name'] ?? '' ) );
            if ( $name ) {
                $section->addText( $name, [ 'bold' => true, 'size' => 20 ] );
            }

            if ( ! empty( $resume['job_title'] ) ) {
                $section->addText( $resume['job_title'], [ 'size' => 14 ] );
            }

            $contact = array_filter( [ $resume['location'] ?? '', $resume['email'] ?? '', $resume['phone'] ?? '' ] );
            if ( $contact ) {
                $section->addText( implode( ' • ', $contact ), [ 'color' => '6b7280', 'size' => 10 ] );
            }

            if ( ! empty( $resume['summary'] ) ) {
                $section->addTextBreak( 1 );
                $section->addText( __( 'Summary', 'resume-ai-toolkit' ), [ 'bold' => true, 'size' => 13 ] );
                $this->add_docx_paragraphs( $section, $resume['summary'] );
            }

            if ( ! empty( $resume['employment'] ) ) {
                $section->addTextBreak( 1 );
                $section->addText( __( 'Experience', 'resume-ai-toolkit' ), [ 'bold' => true, 'size' => 13 ] );

                foreach ( $resume['employment'] as $role ) {
                    $meta  = trim( sprintf( '%s • %s', $role['title'] ?? '', $role['company'] ?? '' ), ' •' );
                    $dates = trim( sprintf( '%s – %s', $role['start'] ?? '', $role['end'] ?? '' ), ' –' );

                    if ( $meta ) {
                        $section->addText( $meta, [ 'bold' => true ] );
                    }
                    if ( $dates ) {
                        $section->addText( $dates, [ 'italic' => true, 'size' => 10 ] );
                    }

                    if ( ! empty( $role['summary'] ) ) {
                        foreach ( preg_split( '/\r?\n/', $role['summary'] ) as $line ) {
                            $line = trim( $line );
                            if ( '' !== $line ) {
                                $section->addText( '- ' . $line );
                            }
                        }
                    }

                    $section->addTextBreak( 1 );
                }
            }

            if ( ! empty( $resume['education'] ) ) {
                $section->addTextBreak( 1 );
                $section->addText( __( 'Education', 'resume-ai-toolkit' ), [ 'bold' => true, 'size' => 13 ] );

                foreach ( $resume['education'] as $edu ) {
                    $meta  = trim( sprintf( '%s, %s', $edu['degree'] ?? '', $edu['school'] ?? '' ), ', ' );
                    $dates = trim( sprintf( '%s – %s', $edu['start'] ?? '', $edu['end'] ?? '' ), ' –' );

                    if ( $meta ) {
                        $section->addText( $meta, [ 'bold' => true ] );
                    }
                    if ( $dates ) {
                        $section->addText( $dates, [ 'italic' => true, 'size' => 10 ] );
                    }
                    $section->addTextBreak( 1 );
                }
            }

            $skills = $resume['skills'] ?? [];
            if ( is_string( $skills ) ) {
                $skills = array_filter( array_map( 'trim', preg_split( '/[,\n]+/', $skills ) ) );
            }

            if ( ! empty( $skills ) && is_array( $skills ) ) {
                $section->addText( __( 'Skills', 'resume-ai-toolkit' ), [ 'bold' => true, 'size' => 13 ] );
                $section->addText( implode( ', ', $skills ) );
            }

            return $this->generate_docx_binary( $php_word );
        }

        /**
         * Build a DOCX export for the enhance flow.
         */
        private function build_enhance_docx( array $analysis ) {
            if ( ! class_exists( PhpWord::class ) ) {
                return new WP_Error( 'resume_ai_docx_missing', __( 'DOCX export dependencies are missing.', 'resume-ai-toolkit' ) );
            }

            $php_word = new PhpWord();
            $php_word->setDefaultFontName( 'Inter' );
            $php_word->setDefaultFontSize( 11 );

            $section = $php_word->addSection(
                [
                    'marginLeft'   => 720,
                    'marginRight'  => 720,
                    'marginTop'    => 720,
                    'marginBottom' => 720,
                ]
            );

            $score = isset( $analysis['score'] ) ? sprintf( __( 'Score: %s/100', 'resume-ai-toolkit' ), (int) $analysis['score'] ) : __( 'Score: --', 'resume-ai-toolkit' );
            $section->addText( __( 'AI Score', 'resume-ai-toolkit' ), [ 'bold' => true, 'size' => 14 ] );
            $section->addText( $score );

            $section->addTextBreak( 1 );
            $section->addText( __( 'Grammar & Clarity', 'resume-ai-toolkit' ), [ 'bold' => true ] );
            $this->add_docx_paragraphs( $section, $analysis['grammar'] ?? '' );

            $section->addTextBreak( 1 );
            $section->addText( __( 'Keywords (ATS)', 'resume-ai-toolkit' ), [ 'bold' => true ] );
            $this->add_docx_paragraphs( $section, $analysis['keywords'] ?? '' );

            $section->addTextBreak( 1 );
            $section->addText( __( 'Formatting & Structure', 'resume-ai-toolkit' ), [ 'bold' => true ] );
            $this->add_docx_paragraphs( $section, $analysis['formatting'] ?? '' );

            if ( ! empty( $analysis['summary'] ) ) {
                $section->addTextBreak( 1 );
                $section->addText( __( 'Executive Summary', 'resume-ai-toolkit' ), [ 'bold' => true ] );
                $this->add_docx_paragraphs( $section, $analysis['summary'] );
            }

            $preview = trim( wp_strip_all_tags( $analysis['resume_document'] ) );
            if ( $preview ) {
                $section->addTextBreak( 1 );
                $section->addText( __( 'Preview', 'resume-ai-toolkit' ), [ 'bold' => true ] );
                $this->add_docx_paragraphs( $section, $preview );
            }

            return $this->generate_docx_binary( $php_word );
        }

        /**
         * Helper to add multi-line paragraphs to DOCX sections.
         */
        private function add_docx_paragraphs( $section, string $text ) {
            foreach ( preg_split( '/\r?\n/', (string) $text ) as $line ) {
                $line = trim( $line );
                if ( '' !== $line ) {
                    $section->addText( $line );
                }
            }
        }

        /**
         * Convert a PhpWord document to a binary string.
         */
        private function generate_docx_binary( PhpWord $document ) {
            try {
                $writer = IOFactory::createWriter( $document, 'Word2007' );
                ob_start();
                $writer->save( 'php://output' );
                return ob_get_clean();
            } catch ( \Exception $exception ) {
                return new WP_Error( 'resume_ai_docx_error', $exception->getMessage() );
            }
        }

        /**
         * Perform the OpenAI HTTP request.
         */
        private function call_openai( array $body ) {
            $api_key = $this->get_api_key();

            if ( ! $api_key ) {
                return new WP_Error( 'resume_ai_missing_key', __( 'OpenAI API key is not configured.', 'resume-ai-toolkit' ) );
            }

            $response = wp_remote_post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'timeout' => 45,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'body'    => wp_json_encode( $body ),
                ]
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $code >= 300 ) {
                $message = $data['error']['message'] ?? __( 'The AI service returned an error.', 'resume-ai-toolkit' );
                return new WP_Error( 'resume_ai_remote_error', $message );
            }

            return $data;
        }

        /**
         * Retrieve API key from settings or constants.
         */
        private function get_api_key() {
            $option = get_option( 'resume_ai_api_key', '' );
            if ( is_string( $option ) ) {
                $option = trim( $option );
            }

            if ( ! empty( $option ) ) {
                return $option;
            }

            if ( defined( 'OPENAI_API_KEY' ) && OPENAI_API_KEY ) {
                return OPENAI_API_KEY;
            }

            return '';
        }

        /**
         * Build cache key.
         */
        private function build_cache_key( string $context, string $hash ) {
            return sprintf( 'resume_ai_%s_%s', $context, $hash );
        }

        private function get_cached_response( string $key ) {
            $cached = get_transient( $key );
            return false === $cached ? null : $cached;
        }

        private function set_cached_response( string $key, array $value ) {
            set_transient( $key, $value, self::CACHE_TTL );
        }

        /**
         * Success response helper.
         */
        private function success_response( array $data, string $message = '', int $status = 200 ) {
            return new WP_REST_Response(
                [
                    'success' => true,
                    'data'    => $data,
                    'message' => $message,
                ],
                $status
            );
        }

        /**
         * Error response helper.
         */
        private function error_response( string $message, int $status = 400, string $code = 'resume_ai_error' ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error'   => $message,
                    'code'    => $code,
                ],
                $status
            );
        }

        /**
         * Determine whether the current user may export resumes.
         */
        public function export_permission() {
            if ( ! is_user_logged_in() ) {
                return false;
            }

            if ( function_exists( 'rai_user_can_download' ) ) {
                return rai_user_can_download( get_current_user_id() );
            }

            return false;
        }
    }
}
