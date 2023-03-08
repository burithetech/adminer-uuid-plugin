<?php

/**
 * This Adminer plugin will format binary-stored UUIDs to human-readable UUID format.
 *
 * @link https://github.com/burithetech/adminer-uuid-plugin
 * @author Peter Burian, https://buri.tech/
 * @version 1.0
 * @license https://opensource.org/licenses/MIT, The MIT License (MIT)
 */

class AdminerUuidPlugin extends Adminer
{

    private const ERROR_PREFIX = 'UuidPlugin:';
    private array $options = [
        'convert_to_lowercase' => true,
        'applicable_to' => [
            'table_names' => '/.*/',
            'column_names' => '/.*/',
            'column_types' => '/^binary\(16\)$/i',
            'comments' => '/.*/'
        ],
    ];

    public function __construct(array $options = [])
    {
        $this->parseOptions($options);
    }

    public function selectSearchPrint($where, $columns, $indexes) {
        $uuidFieldsToPrint = array_filter(
            fields($this->getSelectedTable()),
            fn (array $field) => $this->isFieldSuitable($field)
        );
        $uuidColumnsToPrint = array_column($uuidFieldsToPrint, 'field');

        $originalWhereConditions = $this->getWhereConditions();
        $temporaryWhereConditions = array_map(function (array $condition) use ($uuidColumnsToPrint) {
            if (!in_array($condition['col'], $uuidColumnsToPrint)) {
                return $condition;
            }

            return array_replace($condition, [
                'val' => $this->formatBinaryUuid($condition['val']),
            ]);
        }, $originalWhereConditions);

        $this->setWhereConditions($temporaryWhereConditions);
        parent::selectSearchPrint($where, $columns, $indexes);
        $this->setWhereConditions($originalWhereConditions);
    }

    public function selectSearchProcess($fields, $indexes) {
        $uuidColumnsToProcess = array_filter(
            $fields,
            fn (array $field) => $this->isFieldSuitable($field)
        );

        $where = array_map(function (array $condition) use ($uuidColumnsToProcess) {
            if (!array_key_exists($condition['col'], $uuidColumnsToProcess)) {
                return $condition;
            }

            return array_replace($condition, [
                'val' => str_replace('-', '', $condition['val']),
            ]);
        }, $this->getWhereConditions());

        $this->setWhereConditions($where);

        return parent::selectSearchProcess($fields, $indexes);
	}

    public function rowDescriptions($rows, $foreignKeys)
    {
        $eligibleFields = [];

        foreach (fields($this->getSelectedTable()) as $field) {
            if (!$this->isFieldSuitable($field)) {
                continue;
            }

            $eligibleFields[] = $field['field'];
        }

        return array_map(function (array $row) use ($eligibleFields) {
            foreach ($row as $columnKey => $columnValue) {
                if (!in_array($columnKey, $eligibleFields)) {
                    continue;
                }

                $row[$columnKey] = $this->formatBinaryUuid($columnValue);
            }

            return $row;
        }, $rows);
    }

    public function getSelectedTable(): string
    {
        return $_GET['select'] ?? throw new Exception(sprintf(
            '%s No table selected.',
            self::ERROR_PREFIX
        ));
    }

    private function parseOptions(array $options): void
    {
        // options: applicable_to
        foreach ($options['applicable_to'] ?? [] as $context => $regEx) {
            if (!array_key_exists($context, $this->options['applicable_to'])) {
                continue;
            }

            if (!is_string($regEx)) {
                continue;
            }

            // validate regex
            if (@preg_match($regEx, '') === false) {
                throw new Exception(sprintf('%s Invalid reg-ex in options->applicable_to->%s',
                    self::ERROR_PREFIX,
                    $context
                ));
            }

            $options['applicable_to'][$context] = $regEx;
        }

        // options: convert_to_lowercase
        if (array_key_exists('convert_to_lowercase', $options)) {
            if (is_bool($options['convert_to_lowercase'])) {
                $this->options['convert_to_lowercase'] = $options['convert_to_lowercase'];
            }
        }
    }

    private function formatBinaryUuid(string $binary): string
    {
        if (strlen($binary) !== 32) {
            return $binary;
        }

        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($binary, 0, 8),
            substr($binary, 8, 4),
            substr($binary, 12, 4),
            substr($binary, 16, 4),
            substr($binary, 20, 12)
        );

        if ($this->options['convert_to_lowercase']) {
            return strtolower($uuid);
        }

        return strtoupper($uuid);
    }

    /**
     * @return array<array{col: string, op: string, val: string}>
     */
    public function getWhereConditions(): array
    {
        return $_GET['where'] ?? [];
    }

    /**
     * @param array<array{col: string, op: string, val: string}> $conditions
     */
    public function setWhereConditions(array $conditions): void
    {
        $_GET['where'] = $conditions;
    }

    /**
     * @param array{field: string, full_type: string, comment: string} $field
     */
    private function isFieldSuitable(array $field): bool {
        $applicableToOptions = $this->options['applicable_to'];

        if (!preg_match($applicableToOptions['table_names'], $this->getSelectedTable())) {
            return false;
        }

        if (!preg_match($applicableToOptions['column_names'], $field['field'])) {
            return false;
        }

        if (!preg_match($applicableToOptions['column_types'], $field['full_type'])) {
            return false;
        }

        if (!preg_match($applicableToOptions['comments'], $field['comment'])) {
            return false;
        }

        return true;
    }

}
