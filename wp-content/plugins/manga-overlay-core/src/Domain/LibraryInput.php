<?php
declare(strict_types=1);

namespace MOL\Domain;

final class LibraryInput
{
	public static function validate(array $input): array
	{
		$result = ['page' => 1, 'per_page' => 24, 'sort' => 'latest_chapter'];
		foreach (['search' => 200, 'source_lang' => 255, 'type' => 255, 'work_status' => 255, 'translation_status' => 24, 'sort' => 32] as $key => $length) {
			if (array_key_exists($key, $input)) {
				if (!is_string($input[$key]) || mb_strlen($input[$key]) > $length) {
					throw Fault::invalid('مرشح المكتبة غير صالح.');
				}
				$result[$key] = $input[$key];
			}
		}
		foreach (['page', 'per_page'] as $key) {
			if (isset($input[$key])) {
				$value = filter_var($input[$key], FILTER_VALIDATE_INT);
				if ($value === false || $value < 1 || ($key === 'per_page' && $value > 100)) {
					throw Fault::invalid('رقم الصفحة أو حجمها غير صالح.');
				}
				$result[$key] = $value;
			}
		}
		if ($result['page'] > intdiv(PHP_INT_MAX, $result['per_page'])) {
			throw Fault::invalid();
		}
		if (!in_array($result['sort'], ['latest_chapter', 'latest_work', 'title_asc', 'most_read'], true)) {
			throw Fault::invalid('ترتيب المكتبة غير صالح.');
		}
		if ($result['sort'] === 'most_read') {
			throw new Fault('mol_sort_unavailable', 'ترتيب الأكثر قراءة غير متاح حاليًا.', 400);
		}
		if (isset($result['translation_status']) && !in_array($result['translation_status'], ['untranslated', 'in_progress', 'completed', 'needs_review'], true)) {
			throw Fault::invalid('حالة الترجمة غير صالحة.');
		}
		if (isset($input['genre'])) {
			$genres = is_string($input['genre']) ? [$input['genre']] : $input['genre'];
			if (!is_array($genres) || !array_is_list($genres) || array_filter($genres, static fn ($value) => !is_string($value))) {
				throw Fault::invalid('قائمة التصنيفات غير صالحة.');
			}
			$result['genre'] = array_values(array_unique($genres));
		}
		return $result;
	}
}
