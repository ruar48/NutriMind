<?php

namespace App\Services\StudentImport;

/**
 * The validated preview of a learner CSV file: every row with its status, plus the totals the preview page shows.
 */
final readonly class ImportPreview
{
    /**
     * @param  list<PreviewRow>  $rows  In file order.
     * @param  bool  $usesNameParts  Whether the file names learners with first_name and last_name columns.
     */
    public function __construct(
        public array $rows,
        public bool $usesNameParts,
    ) {}

    /**
     * Get the number of rows with the status (every row when null).
     */
    public function count(?ImportRowStatus $status = null): int
    {
        return count($this->rowsWith($status));
    }

    /**
     * Get the rows with the status (every row when null), in file order.
     *
     * @return list<PreviewRow>
     */
    public function rowsWith(?ImportRowStatus $status): array
    {
        if ($status === null) {
            return $this->rows;
        }

        return array_values(array_filter($this->rows, fn (PreviewRow $row): bool => $row->status === $status));
    }

    /**
     * Get the number of learners the import creates or enrolls.
     */
    public function importableCount(): int
    {
        return $this->count(ImportRowStatus::Create) + $this->count(ImportRowStatus::Enroll);
    }

    /**
     * Determine whether committing the import would change anything.
     */
    public function hasImportableRows(): bool
    {
        return $this->importableCount() > 0;
    }

    /**
     * Get the number of parent accounts the import creates.
     */
    public function parentAccountsToCreate(): int
    {
        return count(array_filter($this->rows, fn (PreviewRow $row): bool => $row->guardian?->action === GuardianAction::CreateAccount));
    }

    /**
     * Get the number of parent–learner links the import adds.
     */
    public function guardianLinksToAdd(): int
    {
        return count(array_filter($this->rows, fn (PreviewRow $row): bool => $row->guardian?->action->createsLink() === true));
    }

    /**
     * Get the row count of every status, keyed by status value, plus "all".
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['all' => count($this->rows)];

        foreach (ImportRowStatus::cases() as $status) {
            $counts[$status->value] = $this->count($status);
        }

        return $counts;
    }
}
