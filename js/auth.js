// js/auth.js — Autenticação e proteção de rotas

// ─── Páginas que exigem login ────────────────────────────────────────────────
const PAGINAS_PROTEGIDAS = [
    'dashboard.html',
    'itens.html',
    'inventarios.html',
    'baixas.html',
    'historicos.html',
    'locais.html',
    'secretarias.html',
    'unidades.html',
    'responsaveis.html',
    'relatorios.html',
    'novo-item.html'
];

// ─── Guard de rota ────────────────────────────────────────────────────────────
(function verificarAcesso() {
    const paginaAtual = window.location.pathname.split('/').pop() || 'index.html';
    const usuario = sessionStorage.getItem('usuario');

    if (PAGINAS_PROTEGIDAS.includes(paginaAtual) && !usuario) {
        // Redireciona para o login e guarda a página de destino
        sessionStorage.setItem('redirecionarPara', window.location.href);
        window.location.replace('index.html');
    }
})();

// ─── Exibe nome do usuário logado no header ───────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const usuarioJson = sessionStorage.getItem('usuario');
    if (!usuarioJson) return;

    try {
        const usuario = JSON.parse(usuarioJson);
        const nomeEl = document.querySelector('.user-name');
        if (nomeEl && usuario.nome) {
            nomeEl.textContent = usuario.nome;
        }
    } catch (e) { /* ignora */ }
});

// ─── Mostrar/ocultar senha ───────────────────────────────────────────────────
function toggleSenha() {
    const senha = document.getElementById('password');
    const icone = document.querySelector('.toggle-password');

    if (!senha || !icone) return;

    if (senha.type === 'password') {
        senha.type = 'text';
        icone.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        senha.type = 'password';
        icone.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ─── Login ───────────────────────────────────────────────────────────────────
async function login(event) {
    event.preventDefault();

    const email = document.getElementById('email')?.value.trim();
    const senha = document.getElementById('password')?.value;
    const btnLogin = document.querySelector('.btn-login');

    if (!email || !senha) {
        mostrarErro('Preencha todos os campos!');
        return;
    }

    // Feedback visual no botão
    if (btnLogin) {
        btnLogin.disabled = true;
        btnLogin.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
    }

    try {
        const response = await fetch('/backend/api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, senha })
        });

        if (!response.ok) {
            throw new Error(`Erro HTTP ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            sessionStorage.setItem('usuario', JSON.stringify(data.usuario));

            // Redireciona para a página de destino (se houver) ou dashboard
            const destino = sessionStorage.getItem('redirecionarPara') || 'dashboard.html';
            sessionStorage.removeItem('redirecionarPara');
            window.location.href = destino;
        } else {
            mostrarErro(data.message || 'E-mail ou senha incorretos!');
        }
    } catch (error) {
        console.error('Erro no login:', error);
        mostrarErro('Erro ao conectar com o servidor. Verifique sua conexão.');
    } finally {
        if (btnLogin) {
            btnLogin.disabled = false;
            btnLogin.innerHTML = '<i class="fas fa-sign-in-alt"></i> Entrar';
        }
    }
}

// ─── Logout ───────────────────────────────────────────────────────────────────
function logout() {
    sessionStorage.removeItem('usuario');
    window.location.replace('index.html');
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function mostrarErro(msg) {
    // Tenta usar elemento de erro já existente; se não houver, usa alert
    let errEl = document.getElementById('login-erro');
    if (errEl) {
        errEl.textContent = msg;
        errEl.style.display = 'block';
        setTimeout(() => { errEl.style.display = 'none'; }, 4000);
    } else {
        alert(msg);
    }
}

// ─── Inicializa formulário de login ──────────────────────────────────────────
const formLogin = document.getElementById('loginForm');
if (formLogin) {
    formLogin.addEventListener('submit', login);
}
