// js/auth.js — Autenticação e proteção de rotas

// ─── Páginas que exigem login ────────────────────────────────────────────────
const PAGINAS_PROTEGIDAS = [
    'dashboard.html',
    'itens.html',
    'inventarios.html',
    'baixas.html',
    'historicos.html',
    'departamentos.html',
    'secretarias.html',
    'unidades.html',
    'responsaveis.html',
    'relatorios.html',
    'novo-item.html',
    'nova-secretaria.html',
    'nova-unidade.html',
    'novo-departamento.html',
    'novo-responsavel.html',
    'novo-inventario.html',
    'nova-baixa.html'
];

// ─── Guard de rota ────────────────────────────────────────────────────────────
(function verificarAcesso() {
    const paginaAtual = window.location.pathname.split('/').pop() || 'index.html';
    const usuario = sessionStorage.getItem('usuario');

    if (PAGINAS_PROTEGIDAS.includes(paginaAtual) && !usuario) {
        sessionStorage.setItem('redirecionarPara', window.location.href);
        window.location.replace('index.html');
        return;
    }

    const parametros = new URLSearchParams(window.location.search);
    const fluxoObrigatorio = {
        'unidades.html': ['secretariaId'],
        'departamentos.html': ['secretariaId', 'unidadeId'],
        'itens.html': ['secretariaId', 'unidadeId', 'departamentoId'],
        'novo-departamento.html': ['secretariaId', 'unidadeId'],
        'novo-item.html': ['secretariaId', 'unidadeId', 'departamentoId']
    };

    if (fluxoObrigatorio[paginaAtual]) {
        const contextoValido = fluxoObrigatorio[paginaAtual].every((campo) => parametros.get(campo));
        if (!contextoValido) {
            window.location.replace('secretarias.html');
        }
    }
})();


function atualizarLinksHierarquia() {
    const params = new URLSearchParams(window.location.search);
    const secretariaId = params.get('secretariaId');
    const unidadeId = params.get('unidadeId');
    const departamentoId = params.get('departamentoId');

    document.querySelectorAll('a[href="unidades.html"]').forEach((link) => {
        link.href = secretariaId ? 'unidades.html?secretariaId=' + encodeURIComponent(secretariaId) : 'secretarias.html';
    });
    document.querySelectorAll('a[href="departamentos.html"]').forEach((link) => {
        link.href = secretariaId && unidadeId
            ? 'departamentos.html?secretariaId=' + encodeURIComponent(secretariaId) + '&unidadeId=' + encodeURIComponent(unidadeId)
            : 'secretarias.html';
    });
    document.querySelectorAll('a[href="itens.html"]').forEach((link) => {
        link.href = secretariaId && unidadeId && departamentoId
            ? 'itens.html?secretariaId=' + encodeURIComponent(secretariaId) + '&unidadeId=' + encodeURIComponent(unidadeId) + '&departamentoId=' + encodeURIComponent(departamentoId)
            : 'secretarias.html';
    });
}

// ─── Exibe nome do usuário logado no header ───────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    atualizarLinksHierarquia();
    const usuarioJson = sessionStorage.getItem('usuario');
    if (!usuarioJson) return;

    try {
        const usuario = JSON.parse(usuarioJson);
        const nomeEl = document.querySelector('.user-name');
        const emailEl = document.querySelector('.user-email');
        const avatarEl = document.querySelector('.user-avatar-dynamic');
        if (nomeEl && usuario.nome) nomeEl.textContent = usuario.nome;
        if (emailEl && usuario.email) emailEl.textContent = usuario.email;
        if (avatarEl && usuario.nome) {
            avatarEl.textContent = usuario.nome.split(' ').filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toUpperCase();
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
        const response = await fetch('backend/api/login.php', {
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
    sessionStorage.clear();
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
