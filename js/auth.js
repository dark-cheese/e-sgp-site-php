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

const PAGINAS_FLUXO = ['unidades.html', 'nova-unidade.html', 'departamentos.html', 'itens.html', 'novo-departamento.html', 'novo-item.html'];
const CHAVE_CONTEXTO_HIERARQUIA = 'hierarquiaContexto';

function paginaAtual() {
    return window.location.pathname.split('/').pop() || 'index.html';
}

function parametrosContexto() {
    return new URLSearchParams(window.location.search);
}

function navegacaoFoiAtualizacao() {
    const nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    return (nav && nav.type === 'reload') || (performance.navigation && performance.navigation.type === 1);
}

function lerContextoHierarquia() {
    try {
        return JSON.parse(sessionStorage.getItem(CHAVE_CONTEXTO_HIERARQUIA) || '{}');
    } catch (e) {
        return {};
    }
}

function definirContextoHierarquia(novoContexto) {
    const contextoAtual = lerContextoHierarquia();
    sessionStorage.setItem(CHAVE_CONTEXTO_HIERARQUIA, JSON.stringify({ ...contextoAtual, ...novoContexto }));
}

function limparContextoHierarquia() {
    sessionStorage.removeItem(CHAVE_CONTEXTO_HIERARQUIA);
}

function contextoNecessario(pagina) {
    const requisitos = {
        'unidades.html': ['secretariaId'],
        'nova-unidade.html': ['secretariaId'],
        'departamentos.html': ['secretariaId', 'unidadeId'],
        'itens.html': ['secretariaId', 'unidadeId', 'departamentoId'],
        'novo-departamento.html': ['secretariaId', 'unidadeId'],
        'novo-item.html': ['secretariaId', 'unidadeId', 'departamentoId']
    };
    return requisitos[pagina] || [];
}

function contextoDaUrl() {
    const params = parametrosContexto();
    return {
        secretariaId: params.get('secretariaId') || '',
        unidadeId: params.get('unidadeId') || '',
        departamentoId: params.get('departamentoId') || ''
    };
}

function contextoValidoParaPagina(pagina) {
    const url = contextoDaUrl();
    const sessao = lerContextoHierarquia();
    return contextoNecessario(pagina).every((campo) => url[campo] && String(sessao[campo] || '') === String(url[campo]));
}

function validarContextoHierarquia() {
    const pagina = paginaAtual();

    if (!PAGINAS_FLUXO.includes(pagina)) {
        limparContextoHierarquia();
        return true;
    }

    if (navegacaoFoiAtualizacao()) {
        limparContextoHierarquia();
        window.location.replace('secretarias.html');
        return false;
    }

    if (!contextoValidoParaPagina(pagina)) {
        limparContextoHierarquia();
        window.location.replace('secretarias.html');
        return false;
    }

    return true;
}

// ─── Guard de rota ────────────────────────────────────────────────────────────
(function verificarAcesso() {
    const pagina = paginaAtual();
    const usuario = sessionStorage.getItem('usuario');

    if (PAGINAS_PROTEGIDAS.includes(pagina) && !usuario) {
        sessionStorage.setItem('redirecionarPara', window.location.href);
        window.location.replace('index.html');
        return;
    }

    validarContextoHierarquia();
})();

function bloquearMenuHierarquico() {
    ['unidades.html', 'departamentos.html', 'itens.html'].forEach((href) => {
        document.querySelectorAll(`a[href^="${href}"]`).forEach((link) => {
            if (link.closest('.breadcrumb')) return;
            link.removeAttribute('href');
            link.setAttribute('aria-disabled', 'true');
            link.classList.add('menu-item-disabled');
            link.addEventListener('click', (event) => event.preventDefault());
        });
    });
}

async function buscarRegistroHierarquia(endpoint, id, filtro) {
    if (!id) return null;
    const qs = filtro ? '?' + new URLSearchParams(filtro).toString() : '';
    const response = await fetch(getApiBaseUrl() + '/' + endpoint + qs);
    const payload = await response.json();
    if (!payload.success) return null;
    return (payload.data || []).find((registro) => String(registro.id) === String(id)) || null;
}

function montarBreadcrumb(partes) {
    const breadcrumb = document.querySelector('.breadcrumb');
    if (!breadcrumb) return;
    breadcrumb.innerHTML = '';
    partes.forEach((parte, index) => {
        if (index > 0) {
            const icone = document.createElement('i');
            icone.className = 'fas fa-chevron-right';
            breadcrumb.appendChild(icone);
        }
        const el = parte.href ? document.createElement('a') : document.createElement('span');
        el.textContent = parte.texto;
        if (parte.href) el.href = parte.href;
        breadcrumb.appendChild(el);
    });
}

async function atualizarBreadcrumbHierarquia() {
    const pagina = paginaAtual();
    if (!PAGINAS_FLUXO.includes(pagina)) return;

    const contexto = contextoDaUrl();
    const partes = [{ texto: 'Secretarias', href: 'secretarias.html' }];

    const secretaria = await buscarRegistroHierarquia('secretarias.php', contexto.secretariaId);
    if (secretaria) partes.push({ texto: secretaria.nome, href: 'unidades.html?secretariaId=' + encodeURIComponent(contexto.secretariaId) });

    if (contexto.unidadeId) {
        const unidade = await buscarRegistroHierarquia('unidades.php', contexto.unidadeId, { secretariaId: contexto.secretariaId });
        if (unidade) partes.push({ texto: unidade.nome, href: 'departamentos.html?secretariaId=' + encodeURIComponent(contexto.secretariaId) + '&unidadeId=' + encodeURIComponent(contexto.unidadeId) });
    }

    if (contexto.departamentoId) {
        const departamento = await buscarRegistroHierarquia('departamentos.php', contexto.departamentoId, { unidadeId: contexto.unidadeId });
        if (departamento) partes.push({ texto: departamento.nome });
    }

    montarBreadcrumb(partes);
}

// ─── Exibe nome do usuário logado no header ───────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    bloquearMenuHierarquico();
    atualizarBreadcrumbHierarquia();
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
