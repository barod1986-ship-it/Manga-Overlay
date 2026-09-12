<?php $works = $args['works'] ?? []; ?>
<?php if (!$works) : ?><p class="mol-empty">لا توجد أعمال تطابق هذه الاختيارات.</p><?php else : ?>
<div class="mol-work-grid"><?php foreach ($works as $work) : ?>
<article class="mol-work-item"><a href="<?php echo esc_url(mol_theme_work_url($work)); ?>"><?php mol_theme_cover($work); ?><h2 dir="auto"><?php echo esc_html($work['title']); ?></h2></a>
<?php $work_status = $work['work_status'] ? get_term_by('slug', $work['work_status'], 'mol_work_status') : null; ?>
<p><?php echo esc_html(MOL\Content\WorkType::TYPES[$work['type']] ?? $work['type']); ?><?php if ($work_status && !is_wp_error($work_status)) : ?> · <?php echo esc_html($work_status->name); ?><?php endif; ?> · <?php echo (int) $work['translation_summary']['total']; ?> فصل</p>
<?php if ($work['translation_summary']['completed']) : ?><small><?php echo (int) $work['translation_summary']['completed']; ?> فصل مكتمل الترجمة</small><?php endif; ?></article>
<?php endforeach; ?></div><?php endif; ?>
