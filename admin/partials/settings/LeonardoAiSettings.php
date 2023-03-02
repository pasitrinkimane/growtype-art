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
    public function general_content()
    {
        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_leonardo_access_key'
        );

        add_settings_field(
            'growtype_ai_leonardo_access_key',
            'Leonardo AI Session - Cookie',
            array ($this, 'growtype_ai_leonardo_access_key_callback'),
            'growtype-ai-settings',
            'growtype_ai_leonardoai_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_leonardo_user_id'
        );

        add_settings_field(
            'growtype_ai_leonardo_user_id',
            'Leonardo AI User - ID',
            array ($this, 'growtype_ai_leonardo_user_id_callback'),
            'growtype-ai-settings',
            'growtype_ai_leonardoai_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_leonardo_access_key_2'
        );

        add_settings_field(
            'growtype_ai_leonardo_access_key_2',
            'Leonardo AI Session 2 - Cookie',
            array ($this, 'growtype_ai_leonardo_access_key_2_callback'),
            'growtype-ai-settings',
            'growtype_ai_leonardoai_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_leonardo_user_id_2'
        );

        add_settings_field(
            'growtype_ai_leonardo_user_id_2',
            'Leonardo AI User 2 - ID',
            array ($this, 'growtype_ai_leonardo_user_id_2_callback'),
            'growtype-ai-settings',
            'growtype_ai_leonardoai_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_leonardo_access_key_3'
        );

        add_settings_field(
            'growtype_ai_leonardo_access_key_3',
            'Leonardo AI Session 3 - Cookie',
            array ($this, 'growtype_ai_leonardo_access_key_3_callback'),
            'growtype-ai-settings',
            'growtype_ai_leonardoai_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_leonardo_user_id_3'
        );

        add_settings_field(
            'growtype_ai_leonardo_user_id_3',
            'Leonardo AI User 3 - ID',
            array ($this, 'growtype_ai_leonardo_user_id_3_callback'),
            'growtype-ai-settings',
            'growtype_ai_leonardoai_settings'
        );
    }

    /**
     *
     */
    function growtype_ai_leonardo_access_key_callback()
    {
        $value = get_option('growtype_ai_leonardo_access_key');
        ?>
        <textarea type="text" rows="10" class="large-text code" name="growtype_ai_leonardo_access_key" value="<?php echo $value ?>"><?php echo $value ?></textarea>
        <?php
    }

    /**
     *
     */
    function growtype_ai_leonardo_user_id_callback()
    {
        $value = get_option('growtype_ai_leonardo_user_id');
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_leonardo_user_id" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_ai_leonardo_access_key_2_callback()
    {
        $value = get_option('growtype_ai_leonardo_access_key_2');
        ?>
        <textarea type="text" rows="10" class="large-text code" name="growtype_ai_leonardo_access_key_2" value="<?php echo $value ?>"><?php echo $value ?></textarea>
        <?php
    }

    /**
     *
     */
    function growtype_ai_leonardo_user_id_2_callback()
    {
        $value = get_option('growtype_ai_leonardo_user_id_2');
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_leonardo_user_id_2" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_ai_leonardo_access_key_3_callback()
    {
        $value = get_option('growtype_ai_leonardo_access_key_3');
        ?>
        <textarea type="text" rows="10" class="large-text code" name="growtype_ai_leonardo_access_key_3" value="<?php echo $value ?>"><?php echo $value ?></textarea>
        <?php
    }

    /**
     *
     */
    function growtype_ai_leonardo_user_id_3_callback()
    {
        $value = get_option('growtype_ai_leonardo_user_id_3');
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_leonardo_user_id_3" value="<?php echo $value ?>"/>
        <?php
    }
}


