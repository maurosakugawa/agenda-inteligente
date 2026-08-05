// src/themes/weatherTheme.types.ts
// Central de interfaces e tipos relacionados ao tema de clima

// Interface para as cores do tema de clima
export interface WeatherTheme {
  background: string; // Cor de fundo
  glass: string; // Cor do efeito de vidro
  cardGlow: string; // Cor do brilho dos cards
  cardBorder: string; // Cor da borda dos cards
  textPrimary: string; // Cor do texto principal
  textSecondary: string; // Cor do texto secundário
  button: string; // Cor dos botões
  buttonHover: string; // Cor dos botões ao passar o mouse
  input: string; // Cor dos campos de entrada
  iconGlow: string; // Cor do brilho dos ícones
  accent: string; // Cor de destaque
  animation: string; // Nome da animação associada ao tema
}
