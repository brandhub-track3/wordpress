<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_menu_page(
        'BrandHub Leads',
        'BrandHub Leads',
        'manage_options',
        'brandhub-leads',
        'brandhub_render_leads_page',
        'dashicons-groups',
        26
    );
});

add_action('admin_post_brandhub_export_leads', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('brandhub_export_leads');

    global $wpdb;

    $table = $wpdb->prefix . 'brandhub_leads';
    $leads = $wpdb->get_results(
        "SELECT id, name, email, company, message, consent, created_at
         FROM $table ORDER BY created_at DESC",
        ARRAY_A
    );

    nocache_headers();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=brandhub-leads.csv');

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'ID', 'Name', 'Email', 'Company',
        'Message', 'Consent', 'Created At'
    ]);

    foreach ($leads as $lead) {
        fputcsv($output, $lead);
    }

    fclose($output);
    exit;
});

function brandhub_render_leads_page(): void
{
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to view leads.');
    }

    $table = $wpdb->prefix . 'brandhub_leads';

    $search = isset($_GET['s'])
        ? sanitize_text_field(wp_unslash($_GET['s']))
        : '';

    $page = max(1, absint($_GET['paged'] ?? 1));
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $where = 'WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= ' AND (name LIKE %s OR email LIKE %s OR company LIKE %s)';
        $params = [$like, $like, $like];
    }

    $count_sql = "SELECT COUNT(*) FROM $table $where";
    $total = (int) $wpdb->get_var(
        $params
            ? $wpdb->prepare($count_sql, ...$params)
            : $count_sql
    );

    $query = "SELECT * FROM $table $where
              ORDER BY created_at DESC
              LIMIT %d OFFSET %d";

    $query_params = array_merge($params, [$per_page, $offset]);

    $leads = $wpdb->get_results(
        $wpdb->prepare($query, ...$query_params)
    );

    ?>
    <div class="wrap">
        <h1>BrandHub Demo Leads</h1>

        <form method="get">
            <input type="hidden" name="page" value="brandhub-leads">

            <input
                type="search"
                name="s"
                placeholder="Search leads"
                value="<?php echo esc_attr($search); ?>"
            >

            <?php submit_button('Search', 'secondary', '', false); ?>
        </form>

        <p>
            <a class="button button-primary"
               href="<?php echo esc_url(
                   wp_nonce_url(
                       admin_url('admin-post.php?action=brandhub_export_leads'),
                       'brandhub_export_leads'
                   )
               ); ?>">
                Export CSV
            </a>
        </p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td><?php echo esc_html($lead->id); ?></td>
                        <td><?php echo esc_html($lead->name); ?></td>
                        <td><?php echo esc_html($lead->email); ?></td>
                        <td><?php echo esc_html($lead->company); ?></td>
                        <td><?php echo esc_html($lead->message); ?></td>
                        <td><?php echo esc_html($lead->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$leads): ?>
                    <tr>
                        <td colspan="6">No leads found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
        $pages = (int) ceil($total / $per_page);

        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';

            echo wp_kses_post(paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $page,
                'total' => $pages
            ]));

            echo '</div></div>';
        }
        ?>
    </div>
    <?php
}