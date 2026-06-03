// ===== FUNÇÕES UTILITÁRIAS =====

// Selecionar todos os checkboxes da tabela
function selecionarTodos(checkbox) {
    document.querySelectorAll('.item-check').forEach(function (item) {
        item.checked = checkbox.checked;
    });
}

// Confirmação antes de excluir
function confirmarExclusao(nome) {
    return confirm('Deseja realmente excluir "' + nome + '"?');
}
