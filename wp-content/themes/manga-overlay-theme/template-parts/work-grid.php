<?php $works = $args['works'] ?? []; ?>
<?php if (!$works) : ?><p class="mol-empty">لا توجد أعمال تطابق هذه الاختيارات.</p><?php else : ?>
<div class="mol-work-grid"><?php foreach ($works as $work) : ?>
<article class="mol-work-item"><a href="<?php echo esc_url(mol_theme_work_url($work)); ?>"><?php mol_theme_cover($work); ?><h2 dir="auto"><?php echo esc_html($work['title']); ?></h2></a>
<p><?php echo esc_html(MOL\Content\WorkType::TYPES[$work['type']] ?? $work['type']); ?> · <?php echo (int) $work['translation_summary']['total']; ?> فصل</p>
<?php if ($work['translation_summary']['completed']) : ?><small><?php echo (int) $work['translation_summary']['completed']; ?> فصل مكتمل الترجمة</small><?php endif; ?></article>
<?php endforeach; ?></div><?php endif; ?>
