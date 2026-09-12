<?php
declare(strict_types=1);

namespace MOL\Domain;

final class Fault extends \RuntimeException
{
	public function __construct(public readonly string $error_code, string $message, public readonly int $status, public readonly array $details = [])
	{
		parent::__construct($message);
	}

	public static function missing(): self
	{
		return new self('mol_not_found', 'المورد غير موجود.', 404);
	}

	public static function invalid(string $message = 'بيانات الطلب غير صالحة.'): self
	{
		return new self('mol_invalid_params', $message, 400);
	}
}
