<?php
// Funções auxiliares para buscar CEP via ViaCEP
function buscarCep($cep) {
    $cep = preg_replace('/[^0-9]/', '', $cep);
    $url = "https://viacep.com.br/ws/{$cep}/json/";
    $json = file_get_contents($url);
    return json_decode($json, true);
}
?>
