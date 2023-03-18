<?php

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
    public function get_access_settings()
    {
        return [
            [
                [
                    'key' => 'growtype_ai_leonardo_access_key',
                    'label' => 'Leonardo AI - User Cookie',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_access_token',
                    'label' => 'Leonardo AI - User Token',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_user_id',
                    'label' => 'Leonardo AI - User ID'
                ]
            ],
            [
                [
                    'key' => 'growtype_ai_leonardo_access_key_2',
                    'label' => 'Leonardo AI - User Cookie 2',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_access_token_2',
                    'label' => 'Leonardo AI - User Token 2',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_user_id_2',
                    'label' => 'Leonardo AI - User ID 2'
                ]
            ],
            [
                [
                    'key' => 'growtype_ai_leonardo_access_key_3',
                    'label' => 'Leonardo AI - User Cookie 3',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_access_token_3',
                    'label' => 'Leonardo AI - User Token 3',
                    'type' => 'textarea'
                ],
                [
                    'key' => 'growtype_ai_leonardo_user_id_3',
                    'label' => 'Leonardo AI - User ID 3'
                ]
            ]
        ];
    }

    public function general_content()
    {
        $access_settings = $this->get_access_settings();

        foreach ($access_settings as $settings_group) {
            foreach ($settings_group as $setting) {
                /**
                 *
                 */
                register_setting(
                    'growtype_ai_settings',
                    $setting['key']
                );

                add_settings_field(
                    $setting['key'],
                    $setting['label'],
                    array (
                        $this,
                        'render_field',
                    ),
                    'growtype-ai-settings',
                    'growtype_ai_leonardoai_settings',
                    $setting
                );
            }
        }
    }

    function render_field($setting)
    {
        $value = get_option($setting['key']);

        if ($setting['type'] === 'textarea') {
            ?>
            <textarea type="text" rows="10" class="large-text code" name="<?php echo $setting['key'] ?>" value="<?php echo $value ?>"><?php echo $value ?></textarea>
            <?php
        } else {
            ?>
            <input type="text" class="regular-text ltr" name="<?php echo $setting['key'] ?>" value="<?php echo $value ?>"/>
            <?php
        }
    }
}


