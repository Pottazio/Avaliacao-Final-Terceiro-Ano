<?php
 class Database
 {
    public static function conectar(): PDO
    {
        $host = "localhost";
        $banco = "pokemon";
        $usuario = "root";
        $senha = "";

        return new PDO(
            "mysql:host=$host;dbname=$banco;charset=utf8mb4",
            $usuario,
            $senha,
            [

                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
            );
 }
}