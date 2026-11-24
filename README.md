# Avalia-o-Final-Terceiro-Ano
Trabalho final das matérias: Banco de dados II, POO e Linguagens para a internet II.  O objetivo do trabalho é criar um site, em PHP (orientado a objetos) e banco de dados, em que se possa jogar uma versão personalizada de Pokémon (ou outro jogo com mesma mecânica).

## Atualização do banco

Em bancos já existentes, aplique `database/movimentos_time.sql`, `database/shiny_time.sql` e `database/perfil_genero.sql` se ainda não foram aplicadas; depois execute `database/views.sql` para criar as três views usadas pela aplicação. Instalações novas já recebem essas tabelas, as colunas shiny/gênero e as views ao executar `database/Bancojogo.sql`.

## Mega Evolução

As Mega Pedras ficam disponíveis no Poké Mart ao chegar à Área 3. Cada perfil pode comprar uma pedra por espécie (Charizardite X/Y contam para o mesmo Pokémon); usá-la em batalha gasta o turno, não consome o item e permite apenas uma Mega Evolução por batalha. A forma e os atributos Mega duram até o fim daquela batalha.

## Status nas batalhas

Movimentos da PokéAPI podem causar queimadura, congelamento, paralisia, envenenamento e sono. Os efeitos persistem ao trocar de Pokémon; queimadura e veneno causam dano residual, e movimentos especiais como Snore e Sleep Talk funcionam durante o sono.
