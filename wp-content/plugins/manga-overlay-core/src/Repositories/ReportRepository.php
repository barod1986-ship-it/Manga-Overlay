<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Domain\Validation\AllowedValues;

final class ReportRepository extends AbstractRepository
{
    /**
     * @param array{
     *   chapter_id: int,
     *   page_id?: int|null,
     *   element_id?: int|null,
     *   reporter_id: int,
     *   report_type: string,
     *   message: string,
     *   status?: string,
     *   resolved_by?: int|null,
     *   created_at?: string,
     *   resolved_at?: string|null
     * } $report
     */
    public function insert(array $report): int
    {
        $this->positiveId($report['chapter_id'], 'chapter_id');
        $this->positiveId($report['reporter_id'], 'reporter_id');
        $status = $report['status'] ?? 'open';
        AllowedValues::reportType($report['report_type']);
        AllowedValues::reportStatus($status);

        return $this->insertRow(
            $this->tables->reports,
            array(
                'chapter_id' => $report['chapter_id'],
                'page_id' => $report['page_id'] ?? null,
                'element_id' => $report['element_id'] ?? null,
                'reporter_id' => $report['reporter_id'],
                'report_type' => $report['report_type'],
                'message' => $report['message'],
                'status' => $status,
                'resolved_by' => $report['resolved_by'] ?? null,
                'created_at' => $report['created_at'] ?? $this->utcNow(),
                'resolved_at' => $report['resolved_at'] ?? null,
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $reportId): ?array
    {
        $this->positiveId($reportId, 'report_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->reports} WHERE id = %d",
            $reportId
        ));

        if (null === $row) {
            return null;
        }
        foreach (array('id', 'chapter_id', 'reporter_id') as $field) {
            $row[$field] = (int) $row[$field];
        }
        foreach (array('page_id', 'element_id', 'resolved_by') as $field) {
            $row[$field] = null === $row[$field] ? null : (int) $row[$field];
        }

        return $row;
    }
}
