<?php
/**
 * Plugin Name: ایکون شناور
 * Plugin URI: https://nakhostinco.ir
 * Description: افزونه ایجاد ایکون‌های شناور مسنجرها با قابلیت تنظیمات کامل
 * Version: 1.1.0
 * Author: فرزاد شیرکوند
 * Author URI: https://nakhostinco.ir
 * Text Domain: floating-icons
 * Domain Path: /languages
 * Company: شرکت نخستین
 */

if (!defined('ABSPATH')) {
    exit;
}

class Floating_Icons_Plugin {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'floating_icons';
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_footer', array($this, 'render_floating_icons'));
        add_action('wp_ajax_save_floating_icon', array($this, 'save_floating_icon'));
        add_action('wp_ajax_delete_floating_icon', array($this, 'delete_floating_icon'));
        add_action('wp_ajax_update_settings', array($this, 'update_settings'));
        add_action('wp_ajax_update_icon_order', array($this, 'update_icon_order'));
    }
    
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            icon_name varchar(255) NOT NULL,
            icon_url varchar(500) NOT NULL,
            messenger_link varchar(500) NOT NULL,
            icon_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        if (!get_option('floating_icons_settings')) {
            add_option('floating_icons_settings', array(
                'position' => 'bottom-right',
                'layout' => 'vertical',
                'icon_size' => '50',
                'spacing' => '10',
                'offset_x' => '20',
                'offset_y' => '20'
            ));
        }
    }
    
    public function deactivate() {
        // حفظ داده‌ها
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'ایکون شناور',
            'ایکون شناور',
            'manage_options',
            'floating-icons',
            array($this, 'admin_page'),
            'dashicons-share',
            30
        );
        
        add_submenu_page(
            'floating-icons',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'floating-icons-settings',
            array($this, 'settings_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'floating-icons') === false) {
            return;
        }
        
        wp_enqueue_style('floating-icons-admin', plugin_dir_url(__FILE__) . 'assets/admin-style.css', array(), '1.1.0');
        wp_enqueue_script('floating-icons-admin', plugin_dir_url(__FILE__) . 'assets/admin-script.js', array('jquery', 'jquery-ui-sortable'), '1.1.0', true);
        wp_enqueue_media();
        wp_localize_script('floating-icons-admin', 'floatingIconsAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('floating_icons_nonce')
        ));
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('floating-icons-frontend', plugin_dir_url(__FILE__) . 'assets/frontend-style.css', array(), '1.1.0');
        wp_enqueue_script('floating-icons-frontend', plugin_dir_url(__FILE__) . 'assets/frontend-script.js', array('jquery'), '1.1.0', true);
    }
    
    public function admin_page() {
        global $wpdb;
        $icons = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY icon_order ASC");
        ?>
        <div class="wrap floating-icons-admin">
            <h1>مدیریت ایکون‌های شناور</h1>
            
            <div class="floating-icons-container">
                <div class="add-icon-form">
                    <h2>افزودن ایکون جدید</h2>
                    <form id="add-icon-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="icon_name">نام مسنجر</label></th>
                                <td><input type="text" id="icon_name" name="icon_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="icon_url">تصویر ایکون</label></th>
                                <td>
                                    <input type="url" id="icon_url" name="icon_url" class="regular-text" required>
                                    <button type="button" class="button" id="upload_icon_btn">آپلود از رسانه</button>
                                    <p class="description">یا آدرس کامل تصویر ایکون را وارد کنید</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="messenger_link">لینک مسنجر</label></th>
                                <td>
                                    <input type="url" id="messenger_link" name="messenger_link" class="regular-text" required>
                                    <p class="description">مثال: https://t.me/username یا https://wa.me/989123456789</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">افزودن ایکون</button>
                        </p>
                    </form>
                </div>
                
                <div class="icons-list">
                    <h2>ایکون‌های موجود (Drag & Drop برای ترتیب)</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ترتیب</th>
                                <th>پیش‌نمایش</th>
                                <th>نام مسنجر</th>
                                <th>لینک</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="icons-list-body">
                            <?php if (empty($icons)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">هیچ ایکونی وجود ندارد</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($icons as $icon): ?>
                                    <tr data-id="<?php echo $icon->id; ?>">
                                        <td class="icon-order"><?php echo $icon->icon_order; ?></td>
                                        <td><img src="<?php echo esc_url($icon->icon_url); ?>" style="width: 40px; height: 40px;"></td>
                                        <td><?php echo esc_html($icon->icon_name); ?></td>
                                        <td><a href="<?php echo esc_url($icon->messenger_link); ?>" target="_blank">مشاهده</a></td>
                                        <td><?php echo $icon->is_active ? 'فعال' : 'غیرفعال'; ?></td>
                                        <td>
                                            <button class="button delete-icon" data-id="<?php echo $icon->id; ?>">حذف</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p class="description">پس از جابجایی، ترتیب به صورت خودکار ذخیره می‌شود.</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        $settings = get_option('floating_icons_settings');
        ?>
        <div class="wrap floating-icons-settings">
            <h1>تنظیمات ایکون شناور</h1>
            
            <form id="settings-form">
                <table class="form-table">
                    <tr>
                        <th><label for="position">موقعیت نمایش</label></th>
                        <td>
                            <select id="position" name="position">
                                <option value="top-left" <?php selected($settings['position'], 'top-left'); ?>>بالا - چپ</option>
                                <option value="top-right" <?php selected($settings['position'], 'top-right'); ?>>بالا - راست</option>
                                <option value="bottom-left" <?php selected($settings['position'], 'bottom-left'); ?>>پایین - چپ</option>
                                <option value="bottom-right" <?php selected($settings['position'], 'bottom-right'); ?>>پایین - راست</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="layout">چیدمان ایکون‌ها</label></th>
                        <td>
                            <select id="layout" name="layout">
                                <option value="vertical" <?php selected($settings['layout'], 'vertical'); ?>>عمودی (بالا سر هم)</option>
                                <option value="horizontal" <?php selected($settings['layout'], 'horizontal'); ?>>افقی (کنار هم)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="icon_size">اندازه ایکون (پیکسل)</label></th>
                        <td>
                            <input type="number" id="icon_size" name="icon_size" value="<?php echo esc_attr($settings['icon_size']); ?>" min="30" max="100">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="spacing">فاصله بین ایکون‌ها (پیکسل)</label></th>
                        <td>
                            <input type="number" id="spacing" name="spacing" value="<?php echo esc_attr($settings['spacing']); ?>" min="0" max="50">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="offset_x">فاصله از لبه افقی (پیکسل)</label></th>
                        <td>
                            <input type="number" id="offset_x" name="offset_x" value="<?php echo esc_attr($settings['offset_x']); ?>" min="0" max="200">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="offset_y">فاصله از لبه عمودی (پیکسل)</label></th>
                        <td>
                            <input type="number" id="offset_y" name="offset_y" value="<?php echo esc_attr($settings['offset_y']); ?>" min="0" max="200">
                        </td>
                    </tr>
                </table>
                
                <div class="floating-icons-nudge">
                    <h3>جابجایی گروهی سریع</h3>
                    <button type="button" class="button nudge" data-dir="up">بالا 10px</button>
                    <button type="button" class="button nudge" data-dir="down">پایین 10px</button>
                    <button type="button" class="button nudge" data-dir="left">چپ 10px</button>
                    <button type="button" class="button nudge" data-dir="right">راست 10px</button>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">ذخیره تنظیمات</button>
                </p>
            </form>
            
            <h2>پیش‌نمایش زنده</h2>
            <div id="floating-icons-live-preview" class="floating-icons-wrapper" data-position="<?php echo esc_attr($settings['position']); ?>" data-layout="<?php echo esc_attr($settings['layout']); ?>" style="
                --icon-size: <?php echo esc_attr($settings['icon_size']); ?>px;
                --spacing: <?php echo esc_attr($settings['spacing']); ?>px;
                --offset-x: <?php echo esc_attr($settings['offset_x']); ?>px;
                --offset-y: <?php echo esc_attr($settings['offset_y']); ?>px;
                position: relative;
            ">
                <a href="#" class="floating-icon"><img src="https://via.placeholder.com/100" alt=""></a>
                <a href="#" class="floating-icon"><img src="https://via.placeholder.com/100/09f/fff" alt=""></a>
                <a href="#" class="floating-icon"><img src="https://via.placeholder.com/100/f90/fff" alt=""></a>
            </div>
        </div>
        <?php
    }
    
    public function save_floating_icon() {
        check_ajax_referer('floating_icons_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        global $wpdb;
        
        $icon_name = sanitize_text_field($_POST['icon_name']);
        $icon_url = esc_url_raw($_POST['icon_url']);
        $messenger_link = esc_url_raw($_POST['messenger_link']);
        
        $max_order = $wpdb->get_var("SELECT MAX(icon_order) FROM {$this->table_name}");
        $new_order = $max_order ? $max_order + 1 : 1;
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'icon_name' => $icon_name,
                'icon_url' => $icon_url,
                'messenger_link' => $messenger_link,
                'icon_order' => $new_order,
                'is_active' => 1
            ),
            array('%s', '%s', '%s', '%d', '%d')
        );
        
        if ($result) {
            wp_send_json_success('ایکون با موفقیت اضافه شد');
        } else {
            wp_send_json_error('خطا در افزودن ایکون');
        }
    }
    
    public function delete_floating_icon() {
        check_ajax_referer('floating_icons_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        global $wpdb;
        $icon_id = intval($_POST['icon_id']);
        
        $result = $wpdb->delete($this->table_name, array('id' => $icon_id), array('%d'));
        
        if ($result) {
            wp_send_json_success('ایکون با موفقیت حذف شد');
        } else {
            wp_send_json_error('خطا در حذف ایکون');
        }
    }
    
    public function update_settings() {
        check_ajax_referer('floating_icons_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        
        $settings = array(
            'position' => sanitize_text_field($_POST['position']),
            'layout' => sanitize_text_field($_POST['layout']),
            'icon_size' => intval($_POST['icon_size']),
            'spacing' => intval($_POST['spacing']),
            'offset_x' => intval($_POST['offset_x']),
            'offset_y' => intval($_POST['offset_y'])
        );
        
        update_option('floating_icons_settings', $settings);
        wp_send_json_success('تنظیمات با موفقیت ذخیره شد');
    }

    public function update_icon_order() {
        check_ajax_referer('floating_icons_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('دسترسی غیرمجاز');
        }
        global $wpdb;
        $order = isset($_POST['order']) ? (array) $_POST['order'] : array();
        $i = 1;
        foreach ($order as $id) {
            $wpdb->update($this->table_name, array('icon_order' => $i), array('id' => intval($id)), array('%d'), array('%d'));
            $i++;
        }
        wp_send_json_success('ترتیب ذخیره شد');
    }
    
    public function render_floating_icons() {
        global $wpdb;
        $icons = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE is_active = 1 ORDER BY icon_order ASC");
        $settings = get_option('floating_icons_settings');
        
        if (empty($icons)) {
            return;
        }
        ?>
        <div class="floating-icons-wrapper" data-position="<?php echo esc_attr($settings['position']); ?>" data-layout="<?php echo esc_attr($settings['layout']); ?>" style="
            --icon-size: <?php echo esc_attr($settings['icon_size']); ?>px;
            --spacing: <?php echo esc_attr($settings['spacing']); ?>px;
            --offset-x: <?php echo esc_attr($settings['offset_x']); ?>px;
            --offset-y: <?php echo esc_attr($settings['offset_y']); ?>px;
        ">
            <?php foreach ($icons as $icon): ?>
                <a href="<?php echo esc_url($icon->messenger_link); ?>" target="_blank" class="floating-icon" data-tooltip="<?php echo esc_attr($icon->icon_name); ?>">
                    <img src="<?php echo esc_url($icon->icon_url); ?>" alt="<?php echo esc_attr($icon->icon_name); ?>">
                    <span class="tooltip"><?php echo esc_html($icon->icon_name); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

new Floating_Icons_Plugin();

/* ====== assets (CSS/JS) ====== */
function floating_icons_create_assets() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $assets_dir = $plugin_dir . 'assets/';
    
    if (!file_exists($assets_dir)) {
        mkdir($assets_dir, 0755, true);
    }
    
    $admin_css = "
.floating-icons-admin .floating-icons-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
    margin-top: 20px;
}
.floating-icons-admin .add-icon-form,
.floating-icons-admin .icons-list {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.floating-icons-admin h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}
.floating-icons-settings {
    max-width: 800px;
}
#icons-list-body tr {
    cursor: move;
}
.floating-icons-nudge {
    margin: 20px 0;
}
#floating-icons-live-preview {
    border: 1px dashed #ccc;
    padding: 20px;
    min-height: 120px;
}
@media (max-width: 782px) {
    .floating-icons-admin .floating-icons-container {
        grid-template-columns: 1fr;
    }
}
";
    file_put_contents($assets_dir . 'admin-style.css', $admin_css);
    
    $admin_js = "
jQuery(document).ready(function($) {
    // media uploader
    $('#upload_icon_btn').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'انتخاب تصویر آیکون',
            button: { text: 'انتخاب' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#icon_url').val(attachment.url);
        });
        frame.open();
    });

    // add icon
    $('#add-icon-form').on('submit', function(e) {
        e.preventDefault();
        var formData = {
            action: 'save_floating_icon',
            nonce: floatingIconsAjax.nonce,
            icon_name: $('#icon_name').val(),
            icon_url: $('#icon_url').val(),
            messenger_link: $('#messenger_link').val()
        };
        $.post(floatingIconsAjax.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert('خطا: ' + response.data);
            }
        });
    });
    
    // delete
    $(document).on('click', '.delete-icon', function() {
        if (!confirm('آیا از حذف این ایکون اطمینان دارید؟')) return;
        var iconId = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(floatingIconsAjax.ajax_url, {
            action: 'delete_floating_icon',
            nonce: floatingIconsAjax.nonce,
            icon_id: iconId
        }, function(response) {
            if (response.success) {
                row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('خطا: ' + response.data);
            }
        });
    });

    // sortable order
    $('#icons-list-body').sortable({
        update: function() {
            var order = [];
            $('#icons-list-body tr').each(function(i){
                order.push($(this).data('id'));
                $(this).find('.icon-order').text(i+1);
            });
            $.post(floatingIconsAjax.ajax_url, {
                action: 'update_icon_order',
                nonce: floatingIconsAjax.nonce,
                order: order
            });
        }
    });

    // settings save
    $('#settings-form').on('submit', function(e) {
        e.preventDefault();
        var formData = {
            action: 'update_settings',
            nonce: floatingIconsAjax.nonce,
            position: $('#position').val(),
            layout: $('#layout').val(),
            icon_size: $('#icon_size').val(),
            spacing: $('#spacing').val(),
            offset_x: $('#offset_x').val(),
            offset_y: $('#offset_y').val()
        };
        $.post(floatingIconsAjax.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data);
            } else {
                alert('خطا: ' + response.data);
            }
        });
    });

    // live preview
    function updatePreview(){
        var wrap = $('#floating-icons-live-preview');
        wrap.attr('data-position', $('#position').val());
        wrap.attr('data-layout', $('#layout').val());
        wrap.css({
            '--icon-size': $('#icon_size').val() + 'px',
            '--spacing': $('#spacing').val() + 'px',
            '--offset-x': $('#offset_x').val() + 'px',
            '--offset-y': $('#offset_y').val() + 'px'
        });
    }
    $('#position, #layout, #icon_size, #spacing, #offset_x, #offset_y').on('input change', updatePreview);

    // nudge buttons
    $('.nudge').on('click', function(){
        var dir = $(this).data('dir');
        var step = 10;
        if(dir === 'up') $('#offset_y').val(Math.max(0, parseInt($('#offset_y').val()||0) - step));
        if(dir === 'down') $('#offset_y').val(parseInt($('#offset_y').val()||0) + step);
        if(dir === 'left') $('#offset_x').val(Math.max(0, parseInt($('#offset_x').val()||0) - step));
        if(dir === 'right') $('#offset_x').val(parseInt($('#offset_x').val()||0) + step);
        updatePreview();
    });

    updatePreview();
});
";
    file_put_contents($assets_dir . 'admin-script.js', $admin_js);
    
    $frontend_css = "
.floating-icons-wrapper {
    position: fixed;
    z-index: 9999;
    display: flex;
}
.floating-icons-wrapper[data-layout='vertical'] { flex-direction: column; gap: var(--spacing); }
.floating-icons-wrapper[data-layout='horizontal'] { flex-direction: row; gap: var(--spacing); }
.floating-icons-wrapper[data-position='top-left'] { top: var(--offset-y); left: var(--offset-x); }
.floating-icons-wrapper[data-position='top-right'] { top: var(--offset-y); right: var(--offset-x); }
.floating-icons-wrapper[data-position='bottom-left'] { bottom: var(--offset-y); left: var(--offset-x); }
.floating-icons-wrapper[data-position='bottom-right'] { bottom: var(--offset-y); right: var(--offset-x); }

.floating-icon {
    position: relative;
    display: block;
    width: var(--icon-size);
    height: var(--icon-size);
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.floating-icon:hover { transform: scale(1.1); box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.floating-icon img { width: 100%; height: 100%; object-fit: cover; }

.floating-icon .tooltip {
    position: absolute;
    background: rgba(0,0,0,0.8);
    color: #fff;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    z-index: 10000;
}
.floating-icons-wrapper[data-position='top-left'] .tooltip,
.floating-icons-wrapper[data-position='bottom-left'] .tooltip {
    left: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
}
.floating-icons-wrapper[data-position='top-right'] .tooltip,
.floating-icons-wrapper[data-position='bottom-right'] .tooltip {
    right: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
}
.floating-icon:hover .tooltip { opacity: 1; }

@media (max-width: 768px) {
    .floating-icons-wrapper { --icon-size: 40px; }
}
";
    file_put_contents($assets_dir . 'frontend-style.css', $frontend_css);
    
    $frontend_js = "
jQuery(document).ready(function($) {
    $('.floating-icon').each(function(index) {
        $(this).css({
            'animation': 'fadeInScale 0.5s ease forwards',
            'animation-delay': (index * 0.1) + 's',
            'opacity': '0'
        });
    });
    if (!$('#floating-icons-animation').length) {
        $('head').append('<style id=\"floating-icons-animation\">@keyframes fadeInScale { from { opacity: 0; transform: scale(0.5); } to { opacity: 1; transform: scale(1); } }</style>');
    }
});
";
    file_put_contents($assets_dir . 'frontend-script.js', $frontend_js);
}

register_activation_hook(__FILE__, 'floating_icons_create_assets');
