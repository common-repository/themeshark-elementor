<?php

namespace Themeshark_Elementor\Inc;



/**
 * Extend your page class from this and create an instance on admin_menu action using the add_menu_page or add_submenu_page function
 */
trait WP_Settings_Page
{
    // PRIVATE PROPERTIES
    //-----------------------------------------------

    private $_current_settings        = [];
    private $_current_section_id      = null;
    private $_current_section_options = [];
    private $_is_first_fields_section = true;
    private $_is_registering_fields   = false;
    private $_did_register_fields     = false;


    // REQUIRED METHODS
    //-----------------------------------------------

    /** Name of the option that will be stored in the wp_options table */
    abstract public function get_option();

    /** Slug for the settings page */
    abstract public function get_slug();

    /** Registers option fields */
    abstract protected function register_fields();


    /** 
     * Registers wp_option and settings fields. Fire this on admin_init action. 
     */
    public function register_settings_fields()
    {
        $wp_option  = $this->get_option();
        $group_name = $this->get_option();


        if ($this->_did_register_fields) return;

        // will be used to set the current value of each option
        $this->_current_settings = get_option($this->get_option());

        // we're using the option as the group name too. 
        $wp_option  = $this->get_option();
        $group_name = $this->get_option();

        // add wp_option to DB. get_option($wp_option) returns an assoc array of the settings registered on the page
        register_setting(
            $group_name,
            $wp_option
        );

        $this->_is_registering_fields = true;
        $this->register_fields();
        $this->_is_registering_fields = false;

        $this->_did_register_fields = true;
    }


    /**
     * Gets the current settings for the options
     */
    public function get_settings($field_id = null)
    {
        $settings = $this->_current_settings;

        if ($field_id === null) {
            return $settings;
        }

        return isset($settings[$field_id]) ? $settings[$field_id] : null;
    }



    /**
     * Creates a new group of fields that can be echoed together using do_fields_section
     */
    protected function start_fields_section($section_id, $label, $section_options = [])
    {
        // Error Handling
        if ($this->_current_section_id !== null) wp_die("you must end fields section $this->_current_section_id before starting $section_id");
        if (!$this->_is_registering_fields)      wp_die('start_fields_section can only be called inside register_fields()');

        // Echo description before doing callback
        $callback = function ($section) use ($section_options) {
            if (isset($section_options['description'])) echo $section_options['description'];
            if (isset($section_options['callback']))    call_user_func($section_options['callback'], $section);
        };

        add_settings_section(
            $section_id,
            $label,
            $callback,
            $this->get_slug()
        );

        $this->_current_section_options = $section_options;
        $this->_current_section_id      = $section_id;
    }

    /**
     * Ends the current fields section 
     */
    protected function end_fields_section()
    {
        if (!$this->_is_registering_fields) wp_die('end_fields_section can only be called inside register_fields()');

        $this->_current_section_options = [];
        $this->_current_section_id = null;
    }


    /**
     * Echos HTML for the fields in the section provided
     */
    protected function do_fields_section($section_id)
    {
        global $wp_settings_sections;

        $page      = $this->get_slug();
        $sections  = self::get_arr_key((array)$wp_settings_sections, $page, null);
        $section   = self::get_arr_key($sections, $section_id, null);

        // Error handling
        if ($sections === null) wp_die("$page does not have any registered sections.");
        if ($section === null)  wp_die("$section_id is not a registered section.");

        if ($this->_is_first_fields_section === true) {

            $group_name = $this->get_option();

            settings_fields($group_name); // prevents directing to wp options page after submit

            $this->_is_first_fields_section = false;
        }

        // Output Fields
        if ($section['title'])    echo "<h2>{$section['title']}</h2>\n";
        if ($section['callback']) call_user_func($section['callback'], $section);

        echo "<table class=\"form-table settings-$section_id\" role=\"presentation\">";
        do_settings_fields($page, $section_id);
        echo '</table>';
    }



    // HELPERS
    //-----------------------------------------------

    /**
     * Gets val from array if set, otherwise returns the $default arg.
     */
    private static function get_arr_key($arr, $key, $default)
    {
        if (!is_array($arr)) return $default;
        return isset($arr[$key]) ? $arr[$key] : $default;
    }


    /**
     * Creates string to be echoed into HTML attributes
     */
    private static function create_attribute_string($atts_arr)
    {
        $atts_string = '';

        foreach ($atts_arr as $att => $val) {

            if (empty($att)) continue;

            $atts_string .= "$att=\"$val\" ";
        }
        return $atts_string;
    }


    /**
     * Gets the HTML name attribute for a setting
     */
    private function get_name_attribute($field_id)
    {
        $wp_option = $this->get_option();
        return "{$wp_option}[$field_id]";
    }


    private function verify_keys($arr, $keys)
    {
        if (!is_array($keys)) $keys = [$keys];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $arr)) {
                wp_die("key $key is required");
            }
        }
    }


    /** 
     * method and post atts automatically included 
     */
    protected function get_form_attribute_string($addon_atts = [])
    {
        $atts = array_merge($addon_atts, [
            'method' => 'post',
            'action' => 'options.php',
        ]);

        $att_string = '';
        foreach ($atts as $att => $val) $att_string .= "$att='$val' ";
        return $att_string;
    }




    // FIELD FUNCTIONS
    //-----------------------------------------------

    private function add_field($field_id, $label, $options, $callback)
    {
        // Error handling
        if (!$this->_current_section_id)    wp_die("You cannot add field $field_id outside of a field section");
        if (!is_string($field_id))          wp_die("field_id must be a string");
        if (!$this->_is_registering_fields) wp_die('field can only be called inside register_fields()');

        // Merge args set in the start_fields_section() function
        $shared_field_options = self::get_arr_key($this->_current_section_options, 'field_options', []);
        $field_options        = array_merge($shared_field_options, $options);

        // Array that is passed to the field callback
        $callback_args = [
            'field_id'  => $field_id,
            'label'     => $label,
            'options'   => $field_options,

            // class and label_for are default args used by wp settings API
            'class'     => self::get_arr_key($field_options, 'class', null),
            'label_for' => self::get_arr_key($field_options, 'label_for', null),
        ];

        // Setting added to DB returned in option array
        add_settings_field(
            $field_id,
            $label,
            $callback,
            $this->get_slug(),
            $this->_current_section_id,
            $callback_args
        );
    }


    /** 
     * CHECKBOX FIELD
     */
    protected function field_checkbox($field_id, $label, $options = [])
    {
        $this->add_field($field_id, $label, $options, function ($field_args) {

            $field_id    = $field_args['field_id'];
            $options     = $field_args['options'];
            $value       = self::get_arr_key($options, 'value', 'yes');
            $description = self::get_arr_key($options, 'description', '');
            $checked     = $this->get_settings($field_id) === $value ? 'checked' : '';

            $atts_string = self::create_attribute_string([
                'type'   => 'checkbox',
                'id'     => $field_id,
                'name'   => $this->get_name_attribute($field_id),
                'value'  => $value,
                $checked => $checked
            ]);
?>
            <label>
                <input <?php echo $atts_string ?> />
                <?php echo $description; ?>
            </label>
        <?php

        });
    }

    /** 
     * RADIO FIELD
     */
    protected function field_radio($field_id, $label, $options = [])
    {
        $this->verify_keys($options, ['options']);

        $this->add_field($field_id, $label, $options, function ($field_args) {

            $field_id       = $field_args['field_id'];
            $options        = $field_args['options'];
            $description    = self::get_arr_key($options, 'description', null);
            $radio_options  = $options['options'];
        ?>
            <fieldset>
                <?php if ($description !== null) echo "$description<br>"; ?>

                <?php foreach ($radio_options as $value => $label) :

                    $checked = $this->get_settings($field_id) === $value ? 'checked' : '';
                    $atts_string = $this->create_attribute_string([
                        'type'   => 'radio',
                        'id'     => $field_id,
                        'name'   => $this->get_name_attribute($field_id),
                        'value'  => $value,
                        $checked => $checked
                    ]);
                ?>
                    <label>
                        <input <?php echo $atts_string; ?> /><?php echo $label; ?>
                    </label>
                    <br>

                <?php endforeach; ?>

            </fieldset>
<?php
        });
    }
}
