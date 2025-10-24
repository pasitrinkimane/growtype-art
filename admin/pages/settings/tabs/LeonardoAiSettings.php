<?php

use partials\Leonardoai_Base;

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    growtype_quiz
 * @subpackage growtype_quiz/admin/partials
 */
class LeonardoAiSettings
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_art_admin_settings_tabs', array ($this, 'settings_tab'));

        add_action('wp_ajax_growtype_art_admin_refresh_cookie', array ($this, 'ai_admin_update_cookie'));
    }

    function ai_admin_update_cookie()
    {
        $user_nr = $_REQUEST['user_nr'] ?? '';

        if (empty($user_nr)) {
            wp_send_json_error('User NR is required');
        }

        $user_creds = Leonardoai_Base::user_credentials($user_nr);

        d($this->login_To_leonardo_ai($user_creds['username'], $user_creds['password']));
    }

    function login_to_leonardo_ai($username, $password) {
        // Define URLs
        $csrfUrl = "https://app.leonardo.ai/api/auth/csrf";
        $loginUrl = "https://app.leonardo.ai/api/auth/callback/credentials";

        // Get WordPress uploads directory path for storing cookies
        $upload_dir = wp_upload_dir();
        $cookie_dir = $upload_dir['basedir'] . '/cookies/leonardoai';

        // Create the directory if it doesn't exist
        if (!file_exists($cookie_dir)) {
            mkdir($cookie_dir, 0755, true);
        }

        // Define path to store cookies
        $cookie_file = $cookie_dir . '/cookie.txt';

        // Step 1: Initialize cURL to get the CSRF token from the API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $csrfUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);  // Save cookies

        // Execute the request to get CSRF token
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return null;
        }

        // Decode the response to get the CSRF token
        $responseData = json_decode($response, true);
        $csrfToken = isset($responseData['csrfToken']) ? $responseData['csrfToken'] : null;

        if (!$csrfToken) {
            echo "Failed to retrieve CSRF token.";
            return null;
        }

        // Close cURL session
        curl_close($ch);

        // Step 2: Use the CSRF token and login with credentials
        $postData = json_encode([
            'username' => $username,
            'password' => $password,
            'redirect' => false,
            'callbackUrl' => 'https://app.leonardo.ai/',
            'csrfToken' => $csrfToken,
            'json' => true
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);  // Send cookies from the file

        // Set headers with CSRF token
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-CSRF-Token: ' . $csrfToken
        ));

        // Execute the login request
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return null;
        }

        // Get response headers and cookies
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);

        // Close cURL session
        curl_close($ch);

        // Return the headers or cookies for further use
        return $headers;
    }

    function settings_tab($tabs)
    {
        $tabs['leonardo'] = 'Leonardo AI';

        return $tabs;
    }

    function admin_settings()
    {
        $access_settings = $this->get_access_settings();

        foreach ($access_settings as $settings_group) {
            foreach ($settings_group as $setting) {
                /**
                 *
                 */
                register_setting(
                    'growtype_art_settings_leonardo',
                    $setting['key']
                );

                add_settings_field(
                    $setting['key'],
                    $setting['label'],
                    array (
                        $this,
                        'render_field',
                    ),
                    Growtype_Art_Admin::SETTINGS_PAGE_NAME,
                    'growtype_art_leonardoai_settings',
                    $setting
                );
            }
        }
    }

    public function get_access_settings()
    {
        return [
            [
                [
                    'key' => 'growtype_art_leonardo_user_id',
                    'label' => 'Leonardo AI - User ID (newcoolstudio@gmail.com)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie',
                    'label' => 'Leonardo AI - Access Token',
                    'type' => 'textarea'
                ],
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_2',
                    'label' => 'Leonardo AI - User ID 2 (hellhoundas@gmail.com)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_2',
                    'label' => 'Leonardo AI - Access Token 2',
                    'type' => 'textarea'
                ],
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_username_3',
                    'label' => 'Leonardo AI - Username 3'
                ],
                [
                    'key' => 'growtype_art_leonardo_password_3',
                    'label' => 'Leonardo AI - Password 3'
                ],
                [
                    'key' => 'growtype_art_leonardo_user_id_3',
                    'label' => 'Leonardo AI - User ID 3 (dev@nuostabu.lt)'
                ],
                [
                    'key' => 'growtype_art_leonardo_refresh_cookie',
                    'label' => 'Refresh Cookie 3',
                    'user_nr' => '3',
                    'type' => 'button-refresh-cookie'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_3',
                    'label' => 'Leonardo AI - Access Token 3',
                    'type' => 'textarea'
                ],
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_4',
                    'label' => 'Leonardo AI - User ID 4 (newcooldev@gmail.com)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_4',
                    'label' => 'Leonardo AI - Access Token 4',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_5',
                    'label' => 'Leonardo AI - User ID 5 (vdirzauskas@gmail.com)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_5',
                    'label' => 'Leonardo AI - Access Token 5',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_6',
                    'label' => 'Leonardo AI - User ID 6 (test@testcool.lt - sMBupkcoqv@#123)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_6',
                    'label' => 'Leonardo AI - Access Token 6',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_7',
                    'label' => 'Leonardo AI - User ID 7 (merbid@talkiemate.com - 1231231234Bb)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_7',
                    'label' => 'Leonardo AI - Access Token 7',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_8',
                    'label' => 'Leonardo AI - User ID 8 (dev@talkiemate.com - D5NxAQvxJd9aFYAn)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_8',
                    'label' => 'Leonardo AI - Access Token 8',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_9',
                    'label' => 'Leonardo AI - User ID 9 (ava@talkiemate.com - cbz26M2XKsoNSafs)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_9',
                    'label' => 'Leonardo AI - Access Token 9',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_10',
                    'label' => 'Leonardo AI - User ID 10 (elara@talkiemate.com - pk1MLhz79WPHRbuUBDGY)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_10',
                    'label' => 'Leonardo AI - Access Token 10',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_11',
                    'label' => 'Leonardo AI - User ID 11 (info@writeglide.com - 1231231234Aa)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_11',
                    'label' => 'Leonardo AI - Access Token 11',
                    'type' => 'textarea'
                ]
            ],
            [
                [
                    'key' => 'growtype_art_leonardo_user_id_12',
                    'label' => 'Leonardo AI - User ID 12 (leo@talkiemate.com - 1231231234Aa%)'
                ],
                [
                    'key' => 'growtype_art_leonardo_cookie_12',
                    'label' => 'Leonardo AI - Access Token 12',
                    'type' => 'textarea'
                ]
            ],
//            [
//                [
//                    'key' => 'growtype_art_leonardo_user_id_13',
//                    'label' => 'Leonardo AI - User ID 13 (talkiemateapp)'
//                ],
//                [
//                    'key' => 'growtype_art_leonardo_cookie_13',
//                    'label' => 'Leonardo AI - Access Token 13',
//                    'type' => 'textarea'
//                ]
//            ]
        ];
    }

    function render_field($setting)
    {
        $value = get_option($setting['key']);

        if (isset($setting['type']) && $setting['type'] === 'textarea') {
            ?>
            <textarea type="text" rows="10" class="large-text code" name="<?php echo $setting['key'] ?>" value="<?php echo $value ?>"><?php echo $value ?></textarea>
            <?php
        } elseif (isset($setting['type']) && $setting['type'] === 'button-refresh-cookie') {
            ?>
            <a href="#" class="button button-secondary btn-refresh-cookie"><?php echo $setting['label'] ?></a>
            <script>
                jQuery(document).ready(function ($) {
                    $('.btn-refresh-cookie').on('click', function (e) {
                        e.preventDefault();

                        $.ajax({
                            type: 'POST',
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            data: {
                                action: 'growtype_art_admin_refresh_cookie',
                                user_nr: '<?php echo $setting['user_nr'] ?>',
                            },
                            success: function (response) {
                                console.log(response);
                            }
                        });
                    });
                });
            </script>
            <?php
        } else {
            ?>
            <input type="text" class="regular-text ltr" name="<?php echo $setting['key'] ?>" value="<?php echo $value ?>"/>
            <?php
        }
    }
}


