public function buscarPorEmail($email)
{
    $sql = "SELECT * FROM usuario WHERE email = :email";

    $stmt = $this->conexao->prepare($sql);

    $stmt->bindValue(":email", $email);

    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}