// ===== FUNÇÕES GERAIS =====

function mostrarData() {
    var data = new Date();
    var dia = String(data.getDate()).padStart(2, '0');
    var mes = String(data.getMonth() + 1).padStart(2, '0');
    var ano = data.getFullYear();
    return dia + '/' + mes + '/' + ano;
}

function saudacao() {
    var hora = new Date().getHours();
    if (hora < 12) return 'Bom dia';
    if (hora < 18) return 'Boa tarde';
    return 'Boa noite';
}

function getApiBaseUrl() {
    var path = window.location.pathname;
    var index = path.indexOf('/frontend/');
    if (index !== -1) {
        return path.substring(0, index) + '/backend/api';
    }
    return '/backend/api';
}

document.addEventListener('DOMContentLoaded', function () {

    // Atualiza data no header
    var dataEl = document.querySelector('.date-display');
    if (dataEl) {
        dataEl.innerHTML = '<i class="far fa-calendar-alt"></i> ' + mostrarData();
    }

    // Saudação no welcome card
    var saudacaoEl = document.querySelector('.welcome-card h2');
    if (saudacaoEl) {
        saudacaoEl.textContent = saudacao() + ', Admin!';
    }

    // Marca menu ativo automaticamente
    var pagina = window.location.pathname.split('/').pop() || 'dashboard.html';
    document.querySelectorAll('.menu-item').forEach(function (item) {
        if (item.getAttribute('href') === pagina) {
            item.classList.add('active');
        }
    });

    // Inicializa filtros, paginação e botões de ação
    inicializarFiltros();
    inicializarPaginacao();
    inicializarAcoes();

});

function inicializarFiltros() {
    document.querySelectorAll('.filter-section').forEach(function (section) {
        var button = section.querySelector('button.btn-primary');
        if (!button) return;

        button.addEventListener('click', function (event) {
            event.preventDefault();
            aplicarFiltroTabela(section);
        });

        var inputs = section.querySelectorAll('input, select');
        inputs.forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    aplicarFiltroTabela(section);
                }
            });
        });
    });
}

function aplicarFiltroTabela(section) {
    var searchInput = section.querySelector('input[type="text"], input[type="search"]');
    var searchText = searchInput ? searchInput.value.trim().toLowerCase() : '';
    var selects = Array.from(section.querySelectorAll('select'));
    var filters = selects
        .map(function (select) {
            var value = select.value.trim().toLowerCase();
            var text = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text.toLowerCase() : '';
            return { value: value, text: text, element: select };
        })
        .filter(function (item) {
            return item.value !== '' && item.value !== 'todos os estados' && item.value !== 'selecione...';
        });

    var table = section.closest('.main-content')?.querySelector('table');
    if (!table) return;

    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function (row) {
        var rowText = row.innerText.toLowerCase();
        var visible = true;

        if (searchText && rowText.indexOf(searchText) === -1) {
            visible = false;
        }

        filters.forEach(function (filter) {
            if (visible && rowText.indexOf(filter.text) === -1) {
                visible = false;
            }
        });

        row.style.display = visible ? '' : 'none';
    });
}

function inicializarPaginacao() {
    document.querySelectorAll('.pagination').forEach(function (pagination) {
        pagination.querySelectorAll('.page-btn').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();

                if (btn.querySelector('i')) {
                    // Botões de navegação anterior/próximo apenas mudam estilo
                    return;
                }

                pagination.querySelectorAll('.page-btn.active').forEach(function (activeBtn) {
                    activeBtn.classList.remove('active');
                });
                btn.classList.add('active');
            });
        });
    });
}

function inicializarAcoes() {
    document.querySelectorAll('.action-btn').forEach(function (button) {
        if (button.hasAttribute('onclick')) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            var text = button.innerText.toLowerCase();
            var iconEye = button.querySelector('.fa-eye');
            var iconEdit = button.querySelector('.fa-edit');
            var iconTag = button.querySelector('.fa-tag');

            if (iconEye || text.includes('ver')) {
                alert('Visualizar detalhes ainda não está implementado.');
                return;
            }
            if (iconEdit || text.includes('editar') || text.includes('edit')) {
                alert('Edição ainda não está implementada.');
                return;
            }
            if (iconTag) {
                alert('Ação de etiqueta/tag ainda não está implementada.');
                return;
            }
            alert('Ação do botão ainda não está implementada.');
        });
    });
}
