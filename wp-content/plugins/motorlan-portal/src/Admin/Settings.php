<?php

namespace MotorlanPortal\Admin;

use MotorlanPortal\Sections;
use MotorlanPortal\Support\Urls;

/**
 * Ajustes › Motorlan Portal: interruptor por sección. Solo escribe la opción al pulsar
 * Guardar (nada al activar el plugin). Si la constante MOTORLAN_PORTAL_SECTIONS está
 * definida, manda ella y el formulario es de solo lectura.
 */
class Settings
{
    const PAGE = 'motorlan-portal';

    public function register()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_motorlan_portal_sections', [$this, 'save']);
    }

    public function menu()
    {
        add_options_page('Motorlan Portal', 'Motorlan Portal', 'manage_options', self::PAGE, [$this, 'page']);
    }

    public function save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permiso.', 'motorlan-portal'), 403);
        }
        check_admin_referer('motorlan_portal_sections');
        if (!Sections::locked()) {
            $registry = Sections::registry();
            $keys = array_map('sanitize_key', (array) ($_POST['sections'] ?? []));
            $keys = array_values(array_filter($keys, function ($k) use ($registry) {
                return isset($registry[$k]) && !empty($registry[$k]['controller']);
            }));
            update_option(Sections::OPTION, $keys, true);
        }
        // Las reglas se regeneran en la siguiente carga (Rewrites::maybe_flush).
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'updated' => 1], admin_url('options-general.php')));
        exit;
    }

    public function page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $requested = Sections::requested();
        $locked    = Sections::locked();
        ?>
        <div class="wrap">
            <h1>Motorlan Portal</h1>
            <p><?php esc_html_e('Cada sección encendida deja de servirla la app Vue y pasa a este plugin. Apagada, la app Vue sigue igual.', 'motorlan-portal'); ?></p>
            <?php if ($locked) : ?>
                <div class="notice notice-info inline"><p><?php echo wp_kses_post(sprintf(__('Gestionado por la constante %s en wp-config.php.', 'motorlan-portal'), '<code>MOTORLAN_PORTAL_SECTIONS</code>')); ?></p></div>
            <?php endif; ?>
            <?php if (!class_exists('Timber\\Timber')) : ?>
                <div class="notice notice-error inline"><p><?php esc_html_e('Falta Timber (composer install): las secciones encendidas mostrarán un aviso en vez de la pantalla.', 'motorlan-portal'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="motorlan_portal_sections">
                <?php wp_nonce_field('motorlan_portal_sections'); ?>
                <table class="form-table" role="presentation"><tbody>
                <?php foreach (Sections::registry() as $key => $s) :
                    $ready = !empty($s['controller']) && class_exists($s['controller']);
                    $on    = in_array($key, $requested, true) && $ready; ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($s['label']); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sections[]" value="<?php echo esc_attr($key); ?>" <?php checked($on); ?> <?php disabled($locked || !$ready); ?>>
                                <code><?php echo esc_html($key); ?></code>
                                <?php if (!$ready) : ?><em>— <?php esc_html_e('sin migrar', 'motorlan-portal'); ?></em><?php endif; ?>
                            </label>
                            <?php if ($on) : ?>
                                <p class="description"><a href="<?php echo esc_url(Urls::portal($key)); ?>"><?php echo esc_html(Urls::portal($key)); ?></a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php if (!$locked) {
                    submit_button();
                } ?>
            </form>
        </div>
        <?php
    }
}
