<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

class ProvinceCatalogService {
	private const PROVINCES = [
		'A Coruña',
		'Álava',
		'Albacete',
		'Alicante',
		'Almería',
		'Asturias',
		'Ávila',
		'Badajoz',
		'Barcelona',
		'Burgos',
		'Cáceres',
		'Cádiz',
		'Cantabria',
		'Castellón',
		'Ceuta',
		'Ciudad Real',
		'Córdoba',
		'Cuenca',
		'Girona',
		'Granada',
		'Guadalajara',
		'Gipuzkoa',
		'Huelva',
		'Huesca',
		'Illes Balears',
		'Jaén',
		'La Rioja',
		'Las Palmas',
		'León',
		'Lleida',
		'Lugo',
		'Madrid',
		'Málaga',
		'Melilla',
		'Murcia',
		'Navarra',
		'Ourense',
		'Palencia',
		'Pontevedra',
		'Salamanca',
		'Santa Cruz de Tenerife',
		'Segovia',
		'Sevilla',
		'Soria',
		'Tarragona',
		'Teruel',
		'Toledo',
		'Valencia',
		'Valladolid',
		'Bizkaia',
		'Zamora',
		'Zaragoza',
	];

	/**
	 * @return string[]
	 */
	public function list(): array {
		return self::PROVINCES;
	}

	public function normalize(?string $province): ?string {
		$trimmed = trim((string) $province);
		if ($trimmed === '') {
			return null;
		}

		$needle = $this->lower($trimmed);
		foreach (self::PROVINCES as $candidate) {
			if ($this->lower($candidate) === $needle) {
				return $candidate;
			}
		}

		return null;
	}

	private function lower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}
}