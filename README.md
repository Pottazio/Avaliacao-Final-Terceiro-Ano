# Avalia-o-Final-Terceiro-Ano
Trabalho final das matérias: Banco de dados II, POO e Linguagens para a internet II.  O objetivo do trabalho é criar um site, em PHP (orientado a objetos) e banco de dados, em que se possa jogar uma versão personalizada de Pokémon (ou outro jogo com mesma mecânica).

## Pokédex do Montar time

A tela Montar time mostra os Pokémon da primeira geração liberados para o perfil. O jogador começa com os números #001–#030; a cada cinco vitórias, mais 30 são liberados, até o limite #151. As vitórias são contadas na tabela `batalhas`, usando `resultado = 'VITORIA'`.

O limite liberado é calculado pelas vitórias do perfil registradas na tabela `batalhas`, usando `resultado = 'VITORIA'`. Não é necessária uma coluna de nível na tabela `time_pokemons`; o número da Pokédex determina quais espécies aparecem e podem ser adicionadas.
