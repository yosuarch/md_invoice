<?php

declare(strict_types=1);

namespace App\Models;

/**
 * region: Indonesian administrative regions (Kepmendagri codes), seeded by RegionSeeder.
 * Level 1 = province (code "73"), level 2 = regency/city (code "73.71", parent_code "73"), level 3 = district / kecamatan
 * (code "73.71.01", parent_code "73.71"). Names are matched case-insensitively (stored addresses are lower case).
 * Reference data, so no audit rows.
 */
class RegionModel extends BaseModel
{
    protected $table          = 'region';
    protected $allowedFields  = ['code', 'parent_code', 'name', 'level'];
    protected bool $audited   = false;

    /**
     * Provinces ordered by name.
     *
     * @return list<array{code: string, name: string}>
     */
    public function provinces(): array
    {
        return $this->select('code, name')->where('level', 1)->orderBy('name')->findAll();
    }

    /**
     * Child regions of a region (regencies/cities of a province), ordered by name.
     *
     * @param string $parentCode Parent code, e.g. "73".
     *
     * @return list<array{code: string, name: string}>
     */
    public function children(string $parentCode): array
    {
        return $this->select('code, name')->where('parent_code', $parentCode)->orderBy('name')->findAll();
    }

    /**
     * Code of a province by its name (to preload the regency list when editing).
     *
     * @param string $name Province name.
     *
     * @return string|null
     */
    public function provinceCode(string $name): ?string
    {
        return $this->childCode(null, $name);
    }

    /**
     * Code of a child region by its name (regency/city of a province, district of a regency/city), case-insensitive.
     *
     * @param string|null $parentCode Parent code (null = provinces).
     * @param string      $name       Region name.
     *
     * @return string|null
     */
    public function childCode(?string $parentCode, string $name): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $this->select('code')->where('LOWER(name)', mb_strtolower($name));
        $parentCode === null ? $this->where('level', 1) : $this->where('parent_code', $parentCode);
        $row = $this->first();

        return $row['code'] ?? null;
    }
}
