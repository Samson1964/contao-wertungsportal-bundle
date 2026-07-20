<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Model;

/**
 * Hilfsfunktion für den API-Abgleich: Feldwerte werden nur in das Model
 * übernommen, wenn sie sich tatsächlich geändert haben. So erzeugen
 * unveränderte Datensätze beim Sync keine UPDATE-Queries mehr.
 */
trait ApiSyncTrait
{
    /**
     * Übernimmt die Feldwerte aus $set in das Model und meldet zurück, ob
     * sich dabei mindestens ein Wert geändert hat. Der Vergleich richtet
     * sich nach dem Typ des neuen Werts (int/float/string), da die
     * Datenbank alle Werte als String liefert.
     */
    protected static function applyApiFields(Model $model, array $set): bool
    {
        $changed = false;

        foreach ($set as $field => $value) {
            $old = $model->{$field};

            if (\is_int($value)) {
                $equal = (int) $old === $value;
            } elseif (\is_float($value)) {
                $equal = (float) $old === $value;
            } else {
                $equal = (string) $old === (string) $value;
            }

            if (!$equal) {
                $model->{$field} = $value;
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Vergleicht die Feldwerte aus $set mit einer Datenbankzeile (row-Array)
     * und liefert nur die tatsächlich geänderten Felder zurück — Grundlage
     * für die Bulk-Sync-Methoden, die ohne Model-Instanzen arbeiten.
     * Der Vergleich richtet sich wie bei applyApiFields() nach dem Typ des
     * neuen Werts (int/float/string).
     */
    protected static function diffApiFields(array $row, array $set): array
    {
        $changed = [];

        foreach ($set as $field => $value) {
            $old = $row[$field] ?? null;

            if (\is_int($value)) {
                $equal = (int) $old === $value;
            } elseif (\is_float($value)) {
                $equal = (float) $old === $value;
            } else {
                $equal = (string) $old === (string) $value;
            }

            if (!$equal) {
                $changed[$field] = $value;
            }
        }

        return $changed;
    }
}
