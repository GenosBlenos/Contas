import re
import nltk
from nltk.corpus import stopwords
from nltk.stem import RSLPStemmer
import unicodedata
import logging

# Configuração de logging
logger = logging.getLogger(__name__)

# --- Inicialização de Recursos NLTK ---
# Garante que os recursos necessários do NLTK sejam baixados se não existirem.
try:
    stopwords.words('portuguese')
except LookupError:
    logger.info("Baixando stopwords do NLTK ('portuguese')...")
    nltk.download('stopwords')

try:
    nltk.data.find('tokenizers/punkt')
except LookupError:
    logger.info("Baixando tokenizador 'punkt' do NLTK...")
    nltk.download('punkt')

# --- Inicialização dos Componentes de Pré-processamento ---
# Estes componentes são inicializados uma vez para serem reutilizados.
stemmer = RSLPStemmer()
stop_words = set(stopwords.words('portuguese'))

def strip_accents(text: str) -> str:
    """
    Remove acentos de uma string, convertendo para a forma mais próxima sem acento.
    Ex: 'olá' -> 'ola'
    """
    try:
        # Normaliza para NFD (Canonical Decomposition) e remove caracteres de combinação (acentos).
        return ''.join(c for c in unicodedata.normalize('NFD', text)
                       if unicodedata.category(c) != 'Mn')
    except TypeError:
        return text # Retorna o texto original se não for uma string

def unified_preprocess_text(text: str) -> str:
    """
    Função unificada de pré-processamento de texto para treino e inferência.
    
    Etapas:
    1. Converte para minúsculas.
    2. Remove acentos.
    3. Remove caracteres não alfabéticos (mantém espaços).
    4. Tokeniza o texto.
    5. Remove stopwords.
    6. Aplica stemming (reduz palavras ao seu radical).
    
    Retorna uma string com o texto pré-processado.
    """
    if not isinstance(text, str):
        logger.warning(f"Entrada para pré-processamento não é uma string (tipo: {type(text)}). Retornando string vazia.")
        return ""

    # 1. Converte para minúsculas
    processed_text = text.lower()
    
    # 2. Remove acentos
    processed_text = strip_accents(processed_text)
    
    # 3. Remove caracteres não alfabéticos
    processed_text = re.sub(r'[^a-z\s]', '', processed_text)
    
    # 4. Tokeniza
    tokens = nltk.word_tokenize(processed_text)
    
    # 5. Remove stopwords e aplica stemming
    processed_tokens = [
        stemmer.stem(token) 
        for token in tokens 
        if token not in stop_words and len(token) > 2
    ]
    
    # Retorna o texto como uma única string
    return " ".join(processed_tokens)
