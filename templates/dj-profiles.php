<?php
// FILE: templates/dj-profiles.php
?>
<div class="musicandlights-dj-profiles">
    <div class="dj-filters" style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
        <h3><?php echo esc_html__('Find Your Perfect DJ', 'musicandlights'); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
            <select id="specialisation-filter" class="form-control">
                <option value=""><?php echo esc_html__('All Event Types', 'musicandlights'); ?></option>
                <option value="wedding"><?php echo esc_html__('Wedding', 'musicandlights'); ?></option>
                <option value="corporate"><?php echo esc_html__('Corporate', 'musicandlights'); ?></option>
                <option value="birthday"><?php echo esc_html__('Birthday Party', 'musicandlights'); ?></option>
                <option value="private"><?php echo esc_html__('Private Party', 'musicandlights'); ?></option>
            </select>
            
            <select id="location-filter" class="form-control">
                <option value=""><?php echo esc_html__('All Areas', 'musicandlights'); ?></option>
                <option value="hertfordshire"><?php echo esc_html__('Hertfordshire', 'musicandlights'); ?></option>
                <option value="london"><?php echo esc_html__('London', 'musicandlights'); ?></option>
                <option value="essex"><?php echo esc_html__('Essex', 'musicandlights'); ?></option>
                <option value="cambridgeshire"><?php echo esc_html__('Cambridgeshire', 'musicandlights'); ?></option>
                <option value="suffolk"><?php echo esc_html__('Suffolk', 'musicandlights'); ?></option>
            </select>
            
            <button type="button" id="filter-djs" class="btn btn-primary"><?php echo esc_html__('Filter DJs', 'musicandlights'); ?></button>
        </div>
    </div>

    <div id="dj-profiles-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
        <?php
        $djs = get_posts([
            'post_type' => 'dj_profile',
            'posts_per_page' => $atts['limit'] ?? 12,
            'post_status' => 'publish'
        ]);
        
        if (empty($djs)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <h3><?php echo esc_html__('No DJs Available Yet', 'musicandlights'); ?></h3>
                <p><?php echo esc_html__('Our DJ profiles are being set up. Please check back soon!', 'musicandlights'); ?></p>
                <?php if (current_user_can('manage_options')): ?>
                    <p><a href="<?php echo admin_url('post-new.php?post_type=dj_profile'); ?>" class="btn btn-primary">
                        <?php echo esc_html__('Add First DJ', 'musicandlights'); ?>
                    </a></p>
                <?php endif; ?>
            </div>
        <?php else: 
            foreach ($djs as $dj):
                $dj_meta = get_post_meta($dj->ID);
                $specialisations = json_decode($dj_meta['dj_specialisations'][0] ?? '[]', true);
                $music_styles = json_decode($dj_meta['dj_music_styles'][0] ?? '[]', true);
                $base_location = $dj_meta['dj_base_location'][0] ?? '';
                $experience_years = $dj_meta['dj_experience_years'][0] ?? '';
                $hourly_rate = $dj_meta['dj_hourly_rate'][0] ?? '';
                $event_rate = $dj_meta['dj_event_rate'][0] ?? '';
                ?>
                <div class="dj-profile-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.3s ease;">
                    <?php if (has_post_thumbnail($dj->ID)): ?>
                        <div style="height: 200px; overflow: hidden;">
                            <?php echo get_the_post_thumbnail($dj->ID, 'medium', ['style' => 'width: 100%; height: 100%; object-fit: cover;']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="padding: 20px;">
                        <h3 style="margin: 0 0 10px 0; color: #333;"><?php echo esc_html($dj->post_title); ?></h3>
                        
                        <?php if ($experience_years): ?>
                            <p style="color: #666; font-size: 14px; margin: 5px 0;">
                                <?php echo esc_html(sprintf(__('%s years experience', 'musicandlights'), $experience_years)); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($base_location): ?>
                            <p style="color: #666; font-size: 14px; margin: 5px 0;">
                                📍 <?php echo esc_html($base_location); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($dj->post_excerpt): ?>
                            <p style="color: #555; font-size: 14px; line-height: 1.5; margin: 10px 0;">
                                <?php echo esc_html(wp_trim_words($dj->post_excerpt, 20)); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($specialisations)): ?>
                            <div style="margin: 15px 0;">
                                <?php foreach (array_slice($specialisations, 0, 3) as $spec): ?>
                                    <span style="display: inline-block; background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; margin: 2px 4px 2px 0;">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $spec))); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                            <div>
                                <?php if ($event_rate): ?>
                                    <span style="font-weight: bold; color: #333; font-size: 18px;">From £<?php echo esc_html(number_format($event_rate)); ?></span>
                                <?php elseif ($hourly_rate): ?>
                                    <span style="font-weight: bold; color: #333; font-size: 18px;">£<?php echo esc_html(number_format($hourly_rate)); ?>/hr</span>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo esc_url(add_query_arg('dj_id', $dj->ID, home_url('/book-dj/'))); ?>" 
                               class="btn btn-primary" style="background: #007cba; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-size: 14px;">
                                <?php echo esc_html__('Book Now', 'musicandlights'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            endforeach;
        endif; ?>
    </div>
</div>

<style>
.dj-profile-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}
.btn-primary {
    background: #007cba;
    color: white;
}
.btn-primary:hover {
    background: #005a87;
    color: white;
}
.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
</style>

