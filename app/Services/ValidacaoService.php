<?php

namespace App\Services;

class ValidacaoService
{
    /**
     * Valida CNPJ ou CPF pelo dígito verificador.
     */
    public static function cnpjOuCpf(string $valor): bool
    {
        $valor = preg_replace('/\D/', '', $valor);

        if (strlen($valor) === 14) {
            return self::validarCnpj($valor);
        }
        if (strlen($valor) === 11) {
            return self::validarCpf($valor);
        }
        return false;
    }

    public static function validarCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) return false;
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += (int) $cnpj[$i] * $pesos1[$i];
        }
        $d1 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ((int) $cnpj[12] !== $d1) return false;

        $soma = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += (int) $cnpj[$i] * $pesos2[$i];
        }
        $d2 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        return (int) $cnpj[13] === $d2;
    }

    public static function validarCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += (int) $cpf[$i] * (10 - $i);
        }
        $d1 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ((int) $cpf[9] !== $d1) return false;

        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += (int) $cpf[$i] * (11 - $i);
        }
        $d2 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        return (int) $cpf[10] === $d2;
    }
}
