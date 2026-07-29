<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Model;
use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;

/**
 * Hilfsfunktion für den API-Abgleich: Feldwerte werden nur in das Model
 * übernommen, wenn sie sich tatsächlich geändert haben. So erzeugen
 * unveränderte Datensätze beim Sync keine UPDATE-Queries mehr.
 */
trait ApiSyncTrait
{
    /**
     * Ergänzt zu jedem gesetzten Quellfeld den zugehörigen Suchalias
     * (Zuordnung Quellfeld => Aliasfeld in der Konstanten ALIAS_FELDER des
     * jeweiligen Models). Ändert sich das Quellfeld nicht, taucht es hier
     * gar nicht erst auf — dann bleibt auch der Alias unangetastet.
     *
     * Damit hängt der Alias an EINER Stelle je Schreibweg statt an jedem
     * einzelnen Feldzugriff. Die Alias-Erzeugung selbst steht in
     * Helper::alias().
     */
    protected static function aliasFelder(array $set): array
    {
        // WICHTIG: Diese Methode gehört NACH den Änderungsvergleich, nicht
        // davor — $set soll nur die tatsächlich geänderten Felder enthalten.
        // Die Alias-Erzeugung kostet rund 0,08 ms je Aufruf; vor dem
        // Vergleich angewandt liefe sie bei jedem Abgleich für jeden
        // Datensatz (eine Vereinsliste mit 765 Mitgliedern käme so auf 1530
        // überflüssige Aufrufe je Seitenaufruf)
        if (!\defined(static::class . '::ALIAS_FELDER')) {
            return $set;
        }

        foreach (static::ALIAS_FELDER as $strQuelle => $strAlias) {
            if (\array_key_exists($strQuelle, $set)) {
                $set[$strAlias] = Helper::alias($set[$strQuelle]);
            }
        }

        return $set;
    }

    /**
     * Ergänzt fehlende Aliase eines Bestandsdatensatzes. Gedacht für
     * Datensätze aus der Zeit vor der Alias-Umstellung: Ändert sich ihr Name
     * nie mehr, käme der Alias sonst nie zustande. So füllt ihn der nächste
     * Sync nebenbei mit — die Migration erledigt den Bestand in einem Rutsch.
     *
     * @param $row Bestandszeile aus der Datenbank (muss Quell- UND Aliasfeld enthalten)
     * @param $set Bereits ermittelte Änderungen
     */
    protected static function fehlendeAliase(array $row, array $set): array
    {
        if (!\defined(static::class . '::ALIAS_FELDER')) {
            return $set;
        }

        foreach (static::ALIAS_FELDER as $strQuelle => $strAlias) {
            if (isset($set[$strAlias]) || !\array_key_exists($strAlias, $row) || !\array_key_exists($strQuelle, $row)) {
                continue;
            }

            if ('' === (string) $row[$strAlias] && '' !== (string) $row[$strQuelle]) {
                $set[$strAlias] = Helper::alias((string) $row[$strQuelle]);
            }
        }

        return $set;
    }

    /**
     * Übernimmt die Feldwerte aus $set in das Model und meldet zurück, ob
     * sich dabei mindestens ein Wert geändert hat. Der Vergleich richtet
     * sich nach dem Typ des neuen Werts (int/float/string), da die
     * Datenbank alle Werte als String liefert.
     */
    protected static function applyApiFields(Model $model, array $set): bool
    {
        $changed = false;
        $arrGeaendert = [];

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
                $arrGeaendert[$field] = true;
                $changed = true;
            }
        }

        // Aliase mitführen: So bekommen alle Model-Schreibwege (upsertByVkz,
        // upsertByUuid, upsertFromPlayerDto) den Alias automatisch mit.
        // Neu erzeugt wird er nur bei geändertem Quellfeld oder wenn er noch
        // ganz fehlt (Datensätze aus der Zeit vor der Umstellung) — sonst
        // liefe die Alias-Erzeugung bei jedem Abgleich für jeden Datensatz
        if (\defined(static::class . '::ALIAS_FELDER')) {
            foreach (static::ALIAS_FELDER as $strQuelle => $strAlias) {
                $strWert = (string) $model->{$strQuelle};

                if (!isset($arrGeaendert[$strQuelle]) && ('' !== (string) $model->{$strAlias} || '' === $strWert)) {
                    continue;
                }

                $strNeu = Helper::alias($strWert);

                if ((string) $model->{$strAlias} !== $strNeu) {
                    $model->{$strAlias} = $strNeu;
                    $changed = true;
                }
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
