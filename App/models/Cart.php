<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Persiste le panier actif d'un utilisateur connecté. */
final class Cart
{
	private bool $officialSchemaAvailable;

	public function __construct(private readonly PDO $database)
	{
		$this->officialSchemaAvailable = $this->hasOfficialSchema();
	}

	/** Récupère le panier unique de l'utilisateur ou le crée à la demande. */
	public function findOrCreateForUser(int $userId): int
	{
		if (!$this->officialSchemaAvailable || $userId < 1) {
			return 0;
		}

		$statement = $this->database->prepare(
			'SELECT id_panier FROM panier WHERE id_utilisateur = :user_id LIMIT 1',
		);
		$statement->execute(['user_id' => $userId]);
		$cartId = (int) $statement->fetchColumn();

		if ($cartId > 0) {
			return $cartId;
		}

		$statement = $this->database->prepare(
			'INSERT INTO panier (id_utilisateur) VALUES (:user_id)',
		);
		$statement->execute(['user_id' => $userId]);

		return (int) $this->database->lastInsertId();
	}

	/** Retourne les variantes et quantités actuellement enregistrées dans le panier. */
	public function lines(int $cartId): array
	{
		if (!$this->officialSchemaAvailable || $cartId < 1) {
			return [];
		}

		$statement = $this->database->prepare(
			'SELECT id_variante AS variant_id, quantite AS quantity
			 FROM ligne_panier
			 WHERE id_panier = :cart_id
			 ORDER BY id_ligne_panier',
		);
		$statement->execute(['cart_id' => $cartId]);

		return $statement->fetchAll();
	}

	/** Fusionne les quantités validées par le service sans remplacer les autres lignes. */
	public function mergeLines(int $cartId, array $quantities): void
	{
		if (!$this->officialSchemaAvailable || $cartId < 1 || $quantities === []) {
			return;
		}

		$this->database->beginTransaction();

		try {
			$statement = $this->database->prepare(
				'INSERT INTO ligne_panier (id_panier, id_variante, quantite)
				 VALUES (:cart_id, :variant_id, :quantity)
				 ON DUPLICATE KEY UPDATE quantite = VALUES(quantite)',
			);

			foreach ($quantities as $variantId => $quantity) {
				$statement->execute([
					'cart_id' => $cartId,
					'variant_id' => (int) $variantId,
					'quantity' => (int) $quantity,
				]);
			}

			$this->database->commit();
		} catch (\Throwable $exception) {
			$this->database->rollBack();
			throw $exception;
		}
	}

	/** Attend la migration officielle avant d'activer la persistance du panier. */
	private function hasOfficialSchema(): bool
	{
		try {
			$cartColumns = $this->database->query('SHOW COLUMNS FROM panier')->fetchAll(PDO::FETCH_COLUMN);
			$lineColumns = $this->database->query('SHOW COLUMNS FROM ligne_panier')->fetchAll(PDO::FETCH_COLUMN);

			return array_diff(['id_panier', 'id_utilisateur'], $cartColumns) === []
				&& array_diff(['id_panier', 'id_variante', 'quantite'], $lineColumns) === [];
		} catch (\PDOException) {
			return false;
		}
	}
}
