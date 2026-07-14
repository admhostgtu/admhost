<?php
/**
 * Contrôleur de base — hérité par tous les contrôleurs MVC.
 */

declare(strict_types=1);

namespace Shared\Core;

abstract class Controller
{
    /**
     * Retourne une réponse JSON (raccourci pour l'API).
     */
    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status)->send();
    }

    /**
     * Rend une vue PHP avec des données.
     */
    protected function view(string $template, array $data = []): never
    {
        $content = View::render($template, $data);
        Response::html($content)->send();
    }

    /**
     * Valide que les champs requis sont présents dans la requête.
     *
     * @param string[] $fields
     */
    protected function validate(Request $request, array $fields): ?array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($request->input($field))) {
                $errors[$field] = "Le champ '$field' est requis.";
            }
        }
        return empty($errors) ? null : $errors;
    }
}
