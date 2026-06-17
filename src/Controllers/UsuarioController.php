<?php

namespace App\Controllers;

class UsuarioController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $stmt = $this->db->query("SELECT id, nome, email, perfil, ativo, trial_expira, created_at FROM usuarios ORDER BY nome");
        $usuarios = $stmt->fetchAll();
        $this->view('pages/usuarios/index', ['pageTitle' => 'Usuários', 'usuarios' => $usuarios, 'flash' => $this->getFlash()]);
    }

    public function novo(): void
    {
        $this->requireAdmin();
        $this->view('pages/usuarios/form', ['pageTitle' => 'Novo Usuário', 'usuario' => null, 'flash' => $this->getFlash()]);
    }

    public function salvar(): void
    {
        $this->requireAdmin();
        $nome         = $this->sanitize($this->post('nome', ''));
        $email        = $this->sanitize($this->post('email', ''));
        $senha        = $this->post('senha', '');
        $perfil       = $this->post('perfil', 'usuario');
        $trialExpira  = $this->post('trial_expira', null) ?: null;

        if (!$nome || !$email || !$senha) {
            $this->flash('erro', 'Nome, e-mail e senha são obrigatórios.');
            $this->redirect('/usuarios/novo');
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("INSERT INTO usuarios (nome, email, senha, perfil, trial_expira) VALUES (?,?,?,?,?)");
        $stmt->execute([$nome, $email, $hash, $perfil, $trialExpira]);

        $this->flash('sucesso', 'Usuário criado com sucesso!');
        $this->redirect('/usuarios');
    }

    public function perfil(): void
    {
        $this->requireLogin();
        $uid  = $_SESSION['usuario']['id'];
        $stmt = $this->db->prepare("SELECT id, nome, email FROM usuarios WHERE id = ?");
        $stmt->execute([$uid]);
        $usuario = $stmt->fetch();
        $this->view('pages/usuarios/perfil', ['pageTitle' => 'Meu Perfil', 'usuario' => $usuario, 'flash' => $this->getFlash()]);
    }

    public function salvarPerfil(): void
    {
        $this->requireLogin();
        $uid  = $_SESSION['usuario']['id'];
        $nome = $this->sanitize($this->post('nome', ''));
        $senha = $this->post('senha', '');

        if (!$nome) {
            $this->flash('erro', 'Nome é obrigatório.');
            $this->redirect('/perfil');
        }

        if ($senha) {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE usuarios SET nome=?, senha=? WHERE id=?");
            $stmt->execute([$nome, $hash, $uid]);
        } else {
            $stmt = $this->db->prepare("UPDATE usuarios SET nome=? WHERE id=?");
            $stmt->execute([$nome, $uid]);
        }

        $_SESSION['usuario']['nome'] = $nome;
        $this->flash('sucesso', 'Perfil atualizado!');
        $this->redirect('/perfil');
    }
}
