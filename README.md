# Avalia-o-Final-Terceiro-Ano
Trabalho final das matérias: Banco de dados II, POO e Linguagens para a internet II. O objetivo do trabalho é criar um site, em PHP (orientado a objetos) e banco de dados, em que se possa jogar uma versão personalizada de Pokémon (ou outro jogo com mesma mecânica).

## Banco de dados

O projeto usa MySQL/MariaDB. Para uma instalação nova, inicie o servidor do
banco e execute `database/Bancojogo.sql`; o script cria e seleciona o banco
`Bancojogo`. No VS Code, o SQLTools com o driver MySQL/MariaDB pode se conectar
ao servidor e executar o arquivo. A extensão é apenas um cliente: ela não
instala nem inicia o servidor.

Se o banco base já existe, execute `database/progressao_batalha.sql` uma única
vez nele. Não execute essa migração novamente depois de aplicada.

Sem variáveis de ambiente, a conexão PHP usa `127.0.0.1:3306`, banco
`Bancojogo`, usuário `root` e senha vazia. `DB_HOST`, `DB_PORT`, `DB_NAME`,
`DB_USER` e `DB_PASS` podem sobrescrever esses valores.

## Progressão e batalhas

O jogo começa na Área Inicial (nível máximo 5, 5 vitórias). As Áreas 1 a 5
liberam níveis máximos de 20 em 20 e exigem, respectivamente, 5, 10, 15, 20 e
25 vitórias. Depois delas, o jogador enfrenta a Elite dos 4.

Cada etapa usa uma lista própria de Pokémon adversários em
`models/ListaPokemonTreinadores.php`. O Pokémon é sorteado da lista da área
atual; as listas ficam mais fortes conforme o jogador avança.

## Pokédex e interface de batalha

A seleção e as equipes são limitadas aos Pokémon da primeira geração
(National Pokédex #001–#151), com validação também no servidor. A busca sugere
nomes por prefixo e permite navegação pelo teclado; a seleção e os cartões da
equipe mostram sprites e os seis atributos básicos obtidos da PokéAPI.

Na batalha, o jogador escolhe entre ataques de dano disponíveis para cada
Pokémon. Sprites, tipos, poder, precisão e categoria dos movimentos são
carregados da PokéAPI; os fundos urbanos são distribuídos entre as batalhas
disponíveis em `public/assets`. A tela inclui a arena, painel de ataques e log
lateral responsivo. O navegador precisa de conexão com a PokéAPI para carregar
as sugestões e os dados de batalha.
