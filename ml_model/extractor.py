import re
from datetime import datetime

def _to_float(valor_str: str) -> float | None:
    """Converte uma string de valor monetário para float."""
    if not valor_str:
        return None
    try:
        # Remove espaços, 'R$', 'TOTAL', pontos de agrupamento e outros símbolos comuns
        valor_limpo = str(valor_str).strip()
        # Remover labels e caracteres não numéricos exceto separadores
        valor_limpo = re.sub(r'[Rr]\$|total|valor|[^0-9,.-]', '', valor_limpo)
        # Normaliza espaços e múltiplos separadores
        # Se ',' aparece e '.' aparece, assume-se que '.' é separador de milhares
        if ',' in valor_limpo and '.' in valor_limpo:
            valor_limpo = valor_limpo.replace('.', '')
            valor_limpo = valor_limpo.replace(',', '.')
        elif ',' in valor_limpo:
            # Apenas vírgula presente: vírgula é separador decimal
            valor_limpo = valor_limpo.replace('.', '').replace(',', '.')
        else:
            # Apenas ponto(s) presentes: último ponto é decimal
            if valor_limpo.count('.') > 1:
                # remove pontos de milhares, deixa o último
                parts = valor_limpo.split('.')
                valor_limpo = ''.join(parts[:-1]) + '.' + parts[-1]
        if valor_limpo in ('', '.', '-'):
            return None
        return float(valor_limpo)
    except (ValueError, TypeError):
        return None

def _to_iso_date(data_str: str) -> str | None:
    """Converte uma string de data (ex: '25/09/2025') para o formato ISO (YYYY-MM-DD)."""
    if not data_str:
        return None
    
    # Remove espaços em branco que o OCR possa ter introduzido
    data_str_limpa = data_str.replace(' ', '')

    # Tenta formatos comuns de data
    for fmt in ('%d/%m/%Y', '%d/%m/%y', '%d.%m.%Y'):
        try:
            return datetime.strptime(data_str_limpa, fmt).strftime('%Y-%m-%d')
        except ValueError:
            pass
    return None

def _find_first_match(text: str, patterns: list[str]) -> str | None:
    """Encontra a primeira correspondência para uma lista de padrões regex."""
    for pattern in patterns:
        match = re.search(pattern, text, re.IGNORECASE | re.DOTALL)
        if match and match.group(1):
            return match.group(1).strip()
    return None


def _normalize_label(text: str) -> str:
    """Normaliza textos para reduzir variações de OCR em labels comuns.

    Exemplos: 'CONSUM.:', 'Consum.:' -> 'CONSUM'
    Remove pontos extras, dois-pontos e espaços duplicados.
    """
    if not text:
        return text
    # Remove caracteres pontuais comuns e múltiplos espaços
    s = re.sub(r'[\.:]+', '', text)
    s = re.sub(r'\s+', ' ', s)
    return s.strip()


def _trim_at_keywords(value: str, keywords_pattern: str) -> str:
    """Retorna a substring de value antes da primeira ocorrência de qualquer keyword no pattern.

    Se não encontrar, retorna value stripado.
    """
    if not value:
        return value
    # procura a primeira ocorrência de qualquer keyword e trunca antes dela
    m = re.search(keywords_pattern, value, re.IGNORECASE)
    if m:
        return value[:m.start()].strip()
    return value.strip()


def get_nome_pagador(text: str) -> str | None:
    """Extrai nome do pagador para faturas de água com tolerância a variações de OCR.

    Procura por rótulos como 'CONSUM', 'Nome', 'Cliente', 'Sacado' e corta em keywords comuns.
    """
    if not text:
        return None

    patterns = [
        r"(?:CONSUM(?:O|IDOR)?|CONSUM\.?|CONSUM\.:)[:\s]*([^\n]+?)\n",
        r"(?:CONSUM(?:O|IDOR)?|CONSUM\.?|CONSUM\.:)[:\s]*([^\n]+)",
        r"Nome[:\s]*(.+?)(?=\n|CEP|CPF|CNPJ)",
        r"Cliente[:\s]*(.+?)(?=\n|CEP|CPF|CNPJ)",
        r"Sacado[:\s]*(.+?)(?=\n|CEP|CPF|CNPJ)",
    ]

    raw = _find_first_match(text, patterns)
    if not raw:
        return None

    keywords = r"\b(CEP|CNPJ|CPF|Nº|Nº DA LIGAÇÃO|LIGAÇÃO:|ENDERECO|ENDEREÇO|ENTREGA|VENCIMENTO|TOTAL|AUTENTICAÇÃO|PAGUE|CONTRATO|AVENIDA|AV\.|RUA|FATURA)\b"
    cleaned = _trim_at_keywords(raw, keywords)
    cleaned = re.sub(r"[\s\-,:]+$", '', cleaned).strip()
    return cleaned if cleaned else None


def get_endereco_pagador(text: str) -> str | None:
    """Extrai endereço do pagador procurando por blocos 'ENTREGA' ou 'Endereço'.

    Junta até duas linhas seguintes quando necessário e corta em keywords comuns.
    """
    if not text:
        return None

    raw = _find_first_match(text, [r"ENTREGA[:\s-]*([^\n]+(?:\n[^\n]+){0,2})", r"ENTREGA[:\s-]*([^\n]+)", r"Endereço[:\s]*([^\n]+)", r"ENDERECO[:\s]*([^\n]+)"])
    if not raw:
        return None

    keywords = r"\b(CEP|PROP|PREFEITURA|CÓDIGO|CÓDIGO DE D[ÉE]BITO|Nº DA LIGAÇÃO|Nº|LIGAÇÃO:|VENCIMENTO|TOTAL|AUTENTICAÇÃO|PAGUE|HIDR|HIDRÔMETRO|LEIT|LEITURA|CONSUMO|EMISS|REFEREN|M[ÉE]DIA|DESCRIÇ)\b"
    cleaned = _trim_at_keywords(raw, keywords)
    cleaned = re.sub(r"\s*\n\s*", ', ', cleaned)
    cleaned = re.sub(r"\s+", ' ', cleaned).strip()
    cleaned = re.sub(r"[\s\-,:]+$", '', cleaned)
    return cleaned if cleaned else None


def get_generic_nome_pagador(text: str, labels: list[str], fallback_patterns: list[str] | None = None) -> str | None:
    """Tenta extrair um nome usando uma lista de labels (ex: ['NOME','Cliente','Sacado']).

    - Tenta capturar texto após cada label até o fim da linha.
    - Se nenhum label encontrar, tenta os fallback_patterns.
    - Corta no primeiro keyword que normalmente termina o campo.
    """
    if not text:
        return None
    patterns = []
    for lbl in labels:
        # captura até fim de linha (inclui rótulos com ou sem dois-pontos)
        patterns.append(rf"{re.escape(lbl)}[:\s]*([^\n]+?)\n")
        patterns.append(rf"{re.escape(lbl)}[:\s]*([^\n]+)")

    if fallback_patterns:
        patterns.extend(fallback_patterns)

    raw = _find_first_match(text, patterns)
    if not raw:
        return None

    # keywords que normalmente aparecem depois do nome do pagador
    keywords = r"\b(CEP|CNPJ|CPF|Nº|Nº DA LIGAÇÃO|LIGAÇÃO:|ENDERECO|ENDEREÇO|ENTREGA|VENCIMENTO|TOTAL|AUTENTICAÇÃO|PAGUE|CONTRATO|CONTRATO N|CONTRATO N°|AVENIDA|AV\.|AV\b|RUA|FATURA|CONTRATO:)\b"
    cleaned = _trim_at_keywords(raw, keywords)
    cleaned = re.sub(r"[\s\-,:]+$", '', cleaned)
    return cleaned if cleaned else None


def get_beneficiario_company(text: str) -> str | None:
    """Tenta localizar o nome da empresa beneficiária através de sufixos comuns (LTDA, S.A., EIRELI, ME)."""
    if not text:
        return None
    # procura por sequências terminando com sufixos empresariais
    patterns = [
        r"([A-Z0-9\s\.&,-]{4,200}?(?:LTDA|Ltda|LTDA\.|S[\.]?A[\.]?|S[\.]?A|EIRELI|ME))",
        r"([A-Z][A-Za-z0-9\s\.&,-]{4,200}?(?:LTDA|S\.A\.|EIRELI|ME))",
    ]
    for p in patterns:
        m = re.search(p, text)
        if m:
            return m.group(1).strip()
    return None


def extract_block_after_header(text: str, header: str, max_lines: int = 3) -> list[str] | None:
    """Localiza um header no texto e retorna as próximas `max_lines` linhas após ele.

    Retorna None se o header não for encontrado.
    """
    if not text:
        return None
    # Normalize newlines
    txt = text.replace('\r\n', '\n').replace('\r', '\n')
    # Procura header (case-insensitive). Usa regex para permitir espaços extras.
    m = re.search(re.escape(header), txt, re.IGNORECASE)
    if not m:
        return None
    # pega substring após o header
    after = txt[m.end():].lstrip('\n ')[:2000]
    lines = after.split('\n')
    # retorna até max_lines não vazias
    out = []
    for ln in lines:
        ln = ln.strip()
        if ln:
            out.append(ln)
        if len(out) >= max_lines:
            break
    return out or None


def get_energy_unidade_consumidora(text: str) -> tuple[str | None, str | None]:
    """Extrai nome_beneficiario e endereco_pagador a partir do bloco 'DADOS DA UNIDADE CONSUMIDORA'.

    Lógica:
    - Localiza o header 'DADOS DA UNIDADE CONSUMIDORA'.
    - A primeira linha útil abaixo do header é `nome_beneficiario`.
    - As próximas 1-2 linhas compõem o `endereco_pagador`.
    """
    block = extract_block_after_header(text, 'DADOS DA UNIDADE CONSUMIDORA', max_lines=4)
    if not block:
        return None, None
    # Normaliza: primeira linha é nome, as seguintes formam o endereço
    nome = block[0].strip() if len(block) >= 1 else None
    # junta as linhas 1..n para formar endereço
    endereco = None
    if len(block) >= 2:
        endereco = ', '.join(part for part in block[1:3] if part)
        endereco = re.sub(r"\s+", ' ', endereco).strip()
    return nome or None, endereco or None


def get_cpfl_fields(text: str) -> tuple[str | None, str | None]:
    """Heurística para faturas CPFL: tenta extrair beneficiário e endereço.

    Estratégia:
    - Confirma presença de 'CPFL' no texto.
    - Localiza linha com 'CNPJ' (ou formato de CNPJ) e coleta linhas vizinhas para nome/endereço.
    - Faz fallback para get_energy_unidade_consumidora se necessário.
    """
    if not text or 'cpfl' not in text.lower():
        return None, None

    txt = text.replace('\r\n', '\n').replace('\r', '\n')
    lines = [ln.strip() for ln in txt.split('\n') if ln.strip()]

    cnpj_re = re.compile(r"\d{2}[.\-]?\d{3}[.\-]?\d{3}/\d{4}[-]?\d{2}")
    cnpj_idx = None
    for i, ln in enumerate(lines):
        if 'cnpj' in ln.lower() or cnpj_re.search(ln):
            cnpj_idx = i
            break

    nome = None
    endereco = None
    if cnpj_idx is not None:
        # Work on the current line containing CNPJ
        cur_line = lines[cnpj_idx]
        # Attempt 1: extract beneficiary name BEFORE the token 'CNPJ' (common in CPFL)
        m_cnpj_token = re.search(r"\bCNPJ\b", cur_line, re.IGNORECASE)
        if m_cnpj_token:
            before = cur_line[:m_cnpj_token.start()].strip()
            # Remove extremely short prefixes like codes but allow short org names (e.g., 'PM SALTO')
            before = re.sub(r"\s{2,}", ' ', before)
            # strip trailing separators
            before = re.sub(r"[\-:;,\s]+$", '', before)
            low = (before or '').lower()
            # ignore if looks like boilerplate
            if before:
                # If the 'before' contains boilerplate, try to extract an organization marker at its tail
                org_markers = [r'\bPM\b', r'\bPREFEITURA\b', r'\bMUNICIPIO\b', r'\bMUNICÍPIO\b', r'\bSECRETARIA\b', r'\bPREF\b', r'\bDROGARIA\b', r'\bCRECHE\b', r'\bTEATRO\b', r'\bMUSEU\b', r'\bAUDIT[ÓO]RIO\b']
                marker_idx = None
                for mk in org_markers:
                    m = re.search(mk, before, re.IGNORECASE)
                    if m:
                        marker_idx = m.start()
                if marker_idx is not None:
                    candidate = before[marker_idx:].strip()
                    candidate = re.sub(r"[\-:;,\s]+$", '', candidate)
                    parts = candidate.split()
                    if len(parts) >= 1:
                        nome = ' '.join(parts[:5])
                else:
                    # fallback: if the last 1-3 tokens look like an org (short/uppercase), take them
                    parts = before.split()
                    tail = parts[-3:]
                    # consider uppercase-like tokens or short tokens
                    if any(t.isupper() or len(t) <= 3 for t in tail):
                        # take last up to 3 tokens
                        nome = ' '.join(tail).strip()
                    else:
                        # as final fallback, use last up to 3 tokens
                        nome = ' '.join(parts[-3:])
                # Special normalization: map 'MUNICIPIO DE <NAME>' -> 'PM <NAME>' when present
                m_mun = re.search(r"municipi\w*\s+de\s+([A-ZÀ-Úa-zà-ú\-\s]+)", before, re.IGNORECASE)
                if m_mun:
                    city = m_mun.group(1).strip()
                    # take first token as short city name
                    city_short = city.split()[0].upper() if city else city.upper()
                    nome = f"PM {city_short}" if city_short else nome

        # Attempt 2: check after the CNPJ number on the same line and prefer as endereco when it looks like an address
        if cnpj_re.search(cur_line):
            m_num = cnpj_re.search(cur_line)
            after = cur_line[m_num.end():].strip()
            # define delimiters that signal end of address/name block
            delimiters = ['INSC', 'INSC.', 'INSC.EST', 'INSCRICAO', 'CLASSIFICA', 'CLASSIFICAÇÃO', 'CEP', 'SERIE', 'SÉRIE', 'PÁG', 'PAG', 'Pág', 'INSTALA', 'DATA', 'WWW', 'HTTP']
            # cut after at first delimiter occurrence
            for d in delimiters:
                idx = re.search(re.escape(d), after, re.IGNORECASE)
                if idx:
                    after = after[:idx.start()].strip()
                    break
            after = re.sub(r"[\-:;,\s]+$", '', after)
            lowa = after.lower()
            if after and not any(bp in lowa for bp in ['solicite os serviços', 'mais informações', 'acesse', 'www.', 'http', '0800']):
                # If after looks like an address (contains digits or comma) prefer as endereco
                if re.search(r'\d', after) or ',' in after or re.search(r'\b(av|avenida|rua|r\.|pca|praca|praça|alameda|travessa|estrada)\b', after, re.IGNORECASE):
                    # set endereco only if not already set
                    if not endereco:
                        endereco = after
                else:
                    # otherwise treat as nome candidate if nome not set
                    if not nome:
                        nome = after

        # If not found in-line, look backwards for a reasonable candidate (expand to 6 lines)
        if not nome:
            for k in range(1, 7):
                j = cnpj_idx - k
                if j < 0:
                    break
                cand = lines[j].strip()
                low = cand.lower()
                # skip obvious boilerplate / contact lines
                if not cand or any(bp in low for bp in ['solicite os serviços', 'mais informações', 'acesse o', 'acesse', 'www.', 'http', '0800', 'telefone', 'fale conosco', 'mantenha seus dados']):
                    continue
                # candidate should have letters and at least 2 words and not be mainly numbers
                if len(cand.split()) >= 2 and re.search(r'[A-Za-zÀ-ú]', cand) and not re.fullmatch(r'[\d\W]+', cand):
                    nome = cand
                    break

        # look forward for address candidate (lines after cnpj) if endereco not yet found
        if not endereco:
            for k in range(1, 6):
                j = cnpj_idx + k
                if j >= len(lines):
                    break
                cand = lines[j]
                lowcand = cand.lower()
                if 'cep' in lowcand or re.search(r'\d{5}-\d{3}', cand) or re.search(r'\d+\s+\w+', cand):
                    # include previous line if it looks like street/name
                    prev = lines[j-1] if j-1 >= 0 else ''
                    endereco = (prev + ', ' + cand).strip() if prev else cand
                    break
                # also if the candidate line contains typical street words
                if re.search(r'\b(av|avenida|rua|r\.|pca|praca|praça|alameda|travessa|estrada)\b', lowcand, re.IGNORECASE):
                    prev = lines[j-1] if j-1 >= 0 else ''
                    endereco = (prev + ', ' + cand).strip() if prev else cand
                    break

    # fallback to energy unit header extraction
    if not nome or not endereco:
        e_nome, e_end = get_energy_unidade_consumidora(text)
        if not nome:
            nome = e_nome
        if not endereco:
            endereco = e_end

    # If still not found, as last resort take first non-boilerplate lines from the top
    boilerplate_phrases = [
        'solicite os serviços disponíveis',
        'mais informações',
        'acesso',
        'acesse o',
    ]
    if not nome:
        # scan first 12 lines for a candidate that is not boilerplate
        for ln in lines[:12]:
            low = ln.lower()
            if any(bp in low for bp in boilerplate_phrases):
                continue
            if len(ln.split()) >= 2 and re.search(r'[A-Za-zÀ-ú]', ln):
                nome = ln
                break

    if nome:
        nome = re.sub(r"[\s\-,:]+$", '', nome).strip()
    if endereco:
        endereco = re.sub(r"\s*\n\s*", ', ', endereco)
        endereco = re.sub(r"[\s\-,:]+$", '', endereco).strip()

    return nome or None, endereco or None


def get_energy_valor_total(text: str) -> str | None:
    """Tenta extrair o valor total de faturas de energia de forma mais precisa."""
    if not text:
        return None

    def _first_monetary_in(fragment: str) -> str | None:
        """Extrai o primeiro valor monetário de um fragmento de texto."""
        if not fragment:
            return None
        # Regex para encontrar valores monetários, priorizando formatos completos
        toks = re.findall(r"(?:R?\$?\s*)([0-9]{1,3}(?:[\.\s]?[0-9]{3}){0,3}(?:[,\.][0-9]{2}))", fragment)
        if toks:
            return toks[0].strip()
        # Fallback para um número simples com duas casas decimais
        toks2 = re.findall(r"([0-9]+[,.][0-9]{2})", fragment)
        return toks2[0].strip() if toks2 else None

    # 1. Busca por labels prioritários e extrai o valor mais próximo
    # Labels comuns para o valor total em faturas de energia
    headers = [
        r"Total a Pagar \(R\$\)", r"Total a Pagar", r"Total Consolidado",
        r"Valor a Pagar", r"TOTAL A PAGAR", r"TOTAL"
    ]
    for header in headers:
        idx = re.search(header, text, re.IGNORECASE)
        if idx:
            # Analisa um pequeno fragmento de texto (80 caracteres) após o label
            frag = text[idx.end(): idx.end() + 80]
            val = _first_monetary_in(frag)
            if val:
                return val

    # 2. Padrões de regex mais específicos que tentam capturar o valor na mesma linha do label
    patterns = [
        r"TOTAL\s+(?:A\s+PAGAR|CONSOLIDADO|DA\s+NOTA)[^\d\n\rR\$]*R?\$?\s*([\d.,]+)",
        r"Total a pagar desta Nota Fiscal.*?([\d.,]+)",
        r"Total Consolidado[:\s]*R?\$?\s*([\d.,]+)",
        r"Total a Pagar[:\s]*R?\$?\s*([\d.,]+)",
        r"TOTAL[:\s]*R?\$?\s*([\d.,]+)",
    ]
    for p in patterns:
        m = re.search(p, text, re.IGNORECASE | re.DOTALL)
        if m and m.group(1):
            return m.group(1).strip()

    # 3. Fallback arriscado: pegar o último valor monetário encontrado no documento
    # Usado como último recurso se as estratégias mais seguras falharem
    all_vals = re.findall(r"R?\$?\s*([0-9]{1,3}(?:[\.\s]?[0-9]{3}){0,3}(?:[,\.][0-9]{2}))", text)
    if all_vals:
        return all_vals[-1]
    
    all_vals2 = re.findall(r"([0-9]+[.,][0-9]{2})", text)
    if all_vals2:
        return all_vals2[-1]
        
    return None



def get_energy_data_vencimento(text: str) -> str | None:
    """Tenta extrair a data de vencimento de faturas de energia.

    Procura por rótulos explícitos, e em fallback retorna a última data encontrada
    no formato DD/MM/YYYY.
    """
    if not text:
        return None
    # Prefer extracting from the 'Total a Pagar' / 'Total Consolidado' block when available
    for header in [r"Total a Pagar", r"Total a Pagar \(R\$\)", r"Total Consolidado", r"Total a Pagar \(R\$\) Data de Vencimento"]:
        idx = re.search(header, text, re.IGNORECASE)
        if idx:
            frag = text[idx.start(): idx.start() + 400]
            m = re.search(r"(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4}).{0,80}?(?:R?\$?\s*)?([0-9]{1,3}(?:[\.\s]?[0-9]{3})*(?:[,\.][0-9]{2}))", frag, re.IGNORECASE | re.DOTALL)
            if m:
                return m.group(1).strip()
            # fallback: return the last date-like token in the fragment
            m2_all = re.findall(r"(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})", frag)
            if m2_all:
                return m2_all[-1].strip()

    # Scan whole text for lines with both value and date and take the last occurrence
    matches = []
    for ln in text.splitlines():
        ln = ln.strip()
        if not ln:
            continue
        m_line = re.search(r"(?:R?\$?\s*)?([0-9]{1,3}(?:[\.\s]?[0-9]{3})*(?:[,\.][0-9]{2})).{0,60}?(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})", ln)
        if m_line:
            matches.append((ln, m_line))
    if matches:
        return matches[-1][1].group(2).strip()

    # Specific heuristic: 'Conta de Energia' block
    m_ce = re.search(r"Conta(?:\s+de)?\s+Energia[^\n\r]{0,300}(?:R?\$?\s*)?([0-9]{1,3}(?:[\.\s]?[0-9]{3})*(?:[,\.][0-9]{2})).{0,120}?(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})", text, re.IGNORECASE | re.DOTALL)
    if m_ce:
        return m_ce.group(2).strip()

    patterns = [
        r"Vencimento[:\s]*(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})",
        r"Data\s+de\s+Vencimento[:\s]*(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})",
        r"Data\s+Vencimento[:\s]*(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})",
        r"VENCIMENTO\s*(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})",
    ]
    for p in patterns:
        m = re.search(p, text, re.IGNORECASE)
        if m and m.group(1):
            return m.group(1).strip()

    # fallback: procurar por datas no documento e retornar a última (com ano 4 dígitos preferencialmente)
    all_dates = re.findall(r"\d{2}[\/\.-]\d{2}[\/\.-]\d{4}", text)
    if all_dates:
        return all_dates[-1]
    # tentar datas com ano de 2 dígitos
    all_dates2 = re.findall(r"\d{2}[\/\.-]\d{2}[\/\.-]\d{2}", text)
    if all_dates2:
        return all_dates2[-1]
    return None

# Definição dos padrões de extração para cada categoria
EXTRACTION_RULES = {
    'agua': {
        'endereco_ligacao': lambda text: get_endereco_pagador(text),
        'referencia': lambda text: _find_first_match(text, [r"Referen[cç]ia[:\s]*([A-Z]{3}/\d{4})", r"REFER\w*[:\s]*([A-Z]{3}/\d{4})", r"(JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ)/\d{4}"]),
        'vencimento': lambda text: _to_iso_date(_find_first_match(text, [r"Vencimento[:\s]*(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})", r"VENCIMENTO[:\s]*(\d{2}[\/\.-]\d{2}[\/\.-]\d{2,4})"])),
        'total_a_pagar': lambda text: _to_float(_find_first_match(text, [r"TOTAL\s*A\s*PAGAR[:\s]*R\$?\s*([\d.,]+)", r"TOTAL[:\s]*R\$?\s*([\d.,]+)", r"TOTAL\s*A\s*PAGAR[:\s]*([\d.,]+)"])),
        'consumo_m3': lambda text: _to_float(_find_first_match(text, [r"Média[\s.]*(\d+)", r"M[eé]dia\s*(?:m3|m³)?[:\s]*(\d+)", r"M[EÉ]DIA[:\s]*(\d+)"])),
        'numero_ligacao': lambda text: _find_first_match(text, [r"Nº\s*DA\s*LIGA[CÇ][AÃ]O[:\s]*(\d+)", r"N[úu]mero\s*da\s*LIGA[CÇ][AÃ]O[:\s]*(\d+)"]),
    },
    'energia': {
        # CPFL (use robust helpers for monetary and date fields)
        'cnpj_pagador': lambda text: _find_first_match(text, [r"CNPJ\s*:\s*([\d./-]+)"]),
        'endereco_consumo': lambda text: (get_cpfl_fields(text)[1] or get_energy_unidade_consumidora(text)[1] or _find_first_match(text, [r"Local\s*:\s*(.*?)(?=CEP)"])),
        'total_a_pagar': lambda text: _to_float(_find_first_match(text, [r"TOTAL\s+A\s+PAGAR\s*R?\$?\s*([\d.,]+)", r"Valor\s+a\s+Pagar\s*R?\$?\s*([\d.,]+)", r"Total\s+Consolidado\s*R?\$?\s*([\d.,]+)"]) or get_energy_valor_total(text)),
        'classificacao': lambda text: _find_first_match(text, [r"Classe\s*[/\\s]*\s*Subclasse\s*[:\s]*(\w+)", r"Classificação[:\s]*(\w+)"]),
        'vencimento': lambda text: _to_iso_date(get_energy_data_vencimento(text)),
        'numero_fatura': lambda text: _find_first_match(text, [r"Conta\s+Contrato\s+N[oº°]\s*([\d-]+)", r"Nota\s+Fiscal\n*[:\s]*([\d.]+)"]),
        'data_emissao': lambda text: _to_iso_date(_find_first_match(text, [r"Data\s+de\s+Emissão[:;]?\s*(\d{2}[\s./-]\d{2}[\s./-]\d{2,4})"])), # Existing
        'conta_mes': lambda text: _find_first_match(text, [ # Lista de padrões para 'conta_mes'
            # Padrões explícitos com labels (mais confiáveis)
            r"Referente\s+a\s*[:\s]*(\d{2}/\d{4})",
            r"M[eê]s\s+de\s+Refer[eê]ncia\s*[:\s]*(\d{2}/\d{4})",
            r"CONTA\s+M[EÊ]S\s*[:\s]*(\d{2}/\d{4})",
            r"Conta\s+M[eê]s\s*[:\s]*(\d{2}/\d{4})",
            r"Compet[êe]ncia\s*[:\s]*(\d{2}/\d{4})",
            r"Per[íi]odo\s*[:\s]*(\d{2}/\d{4})",
            r"Refer[êe]ncia\s*[:\s]*(\d{2}/\d{4})",
            # Padrões que buscam o mês/ano próximo a outros campos-chave
            r"Vencimento\s+\d{2}/\d{2}/\d{4}\s+(\d{2}/\d{4})", # Vencimento 25/01/2023 01/2023
            r"(\d{2}/\d{4})\s+Vencimento", # 01/2023 Vencimento
            # Padrão para mês por extenso (ex: JANEIRO/2023)
            r"\b(JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ)[A-Z]*\s*/\s*(\d{4})\b",
            # Padrão genérico para capturar MM/YYYY que aparece sozinho em uma linha
            r"^\s*(\d{2}/\d{4})\s*$",
        ]), # Fim da lista de padrões
        'codigo_instalacao': lambda text: _find_first_match(text, [r"www\\.cpfl\\.com\\.br\s+(\d+)", r"Conta\s+Contrato\s+N[oº°]\s*([\d-]+)", r"C[OÓ]DIGO DE INSTALA[CÇ][AÃ]O\s*[:\s]*(\d+)", r"INSTALAÇÃO\s*[:\s]*(\d+)", r"INSTALA[CÇ][AÃ]O\s*[:\s]*(\d+)"]),
        'consumo_kwh': lambda text: _to_float(_find_first_match(text, [r"\\d{4}Consumo Uso Sistema \\[KWh\\]-TUSD.*?([\\d.,]+)", r"Consumo\\s+TUSD\\s+TE[\\s\\S]*?Consumo\\s+kWh\\s+.*?([\\d.,]+)", r"Consumo\\s+\\(kWh\\)\\s*[:\\s]*([\\d.,]+)", r"Consumo\\s*kWh\\s*[:\\s]*([\\d.,]+)", r"Consumo\\s+em\\s+kWh\\s*[:\\s]*([\\d.,]+)", r"Consumo\\s*[:\\s]*([\\d.,]+)\\s*kWh"])),
        'fat_impostos': lambda text: _to_float(_find_first_match(text, [r"Fat\.\s+Impostos\s*\(R\$\)\s*([\d.,]+)"])),
        'fat_distribuidora': lambda text: _to_float(_find_first_match(text, [r"Fat\.\s+Distribuidora\s*\(R\$\)\s*([\d.,]+)"])),
        'multa_atraso': lambda text: _to_float(_find_first_match(text, [r"Multa\s+por\s+Atraso\s*\(R\$\)\s*([\d.,]+)"])),
        'imposto_retido_total': lambda text: _to_float(_find_first_match(text, [r"Imposto\s+Retido[:\s]*TOTAL\s*\(R\$\)\s*([\d.,]+)"])),
        'imposto_retido_irrf': lambda text: _to_float(_find_first_match(text, [r"Imposto\s+Retido[:\s]*ret\.\s+out\.\s+fornec\s+irrf\s+-1,2%\s*([\d.,]+)"])),
    },
    'telefone': {
        'cnpj_beneficiario': lambda text: _find_first_match(text, [r"CNPJ:\s*([\d./-]+)"]),
        'nome_pagador': lambda text: _find_first_match(text, [r"^(.*?)(?=\s*VENCIMENTO)"]),
        'cnpj_pagador': lambda text: _find_first_match(text, [r"CPF/CNPJ\s*:\s*([\d./-]+)"]),
        'endereco_pagador': lambda text: _find_first_match(text, [r"TOTAL\s+DA\s+FATURA\s+(.*?)\s+\d{2}\.\d{2}\.\d{4}"]),
        'numero_fatura': lambda text: _find_first_match(text, [r"FATURA\s+N[º°]\s*:\s*(\d+)"]),
        'data_emissao': lambda text: _to_iso_date(_find_first_match(text, [r"Data\s+da\s+emissão[:;]?\s*(\d{2}[\\s./-]\d{2}[\\s./-]\d{2,4})"])),
        'data_vencimento': lambda text: _to_iso_date(_find_first_match(text, [r"TOTAL\s+DA\s+FATURA\s+.*?\s+(\d{2}\.\d{2}\.\d{4})", r"Vencimento[:;]?\s*(\d{2}[\s./-]\d{2}[\s./-]\d{2,4})"])),
        'valor_total': lambda text: _to_float(_find_first_match(text, [r"\d{2}\.\d{2}\.\d{4}\s+R\$\s*([\d.,]+)", r"Total\s+a\s+pagar\s+R\$\s*([\d.,]+)"])),
        'descricao_servico': lambda text: _find_first_match(text, [r"Serviços\s+contratados\s+Qtde\s+Período\s+Valor\s+(.*?)\s+\d+\s+\d{2}/\d{2}"]),
        'periodo_servico': lambda text: _find_first_match(text, [r"(\d{2}/\d{2}\s+a\s+\d{2}/\d{2})"]),
        'valor_servico': lambda text: _to_float(_find_first_match(text, [r"Valor\s+dos\s+Serviços\s*R?\$\s*([\d.,]+)", r"Total\s+a\s+pagar\s+R\$\s*([\d.,]+)"])),
    },
    'internet': {
        # Regras genéricas para Internet (ex: Best Fibra, etc.)
        'cnpj_beneficiario': lambda text: _find_first_match(text, [r"CNPJ:\s*([\d./-]+)"]),
        'nome_pagador': lambda text: get_generic_nome_pagador(text, ['Cliente', 'Assinante', 'Sacado', 'Nome']),
        'cnpj_pagador': lambda text: _find_first_match(text, [r"Cliente:.*?CNPJ:\s*([\d./-]+)", r"CNPJ/CPF\s*do\s*Cliente[:\s]*([\d./-]+)"]),
        'endereco_pagador': lambda text: _find_first_match(text, [r"Endereço:\s*(.*?)(?=CEP|Bairro|Cidade)"]),
        'numero_fatura': lambda text: _find_first_match(text, [r"Fatura\s+Nº\s*[:\s]*([\d.]+)", r"NOTA FISCAL Nº\s*([\d.]+)"]),
        'data_emissao': lambda text: _to_iso_date(_find_first_match(text, [r"Data\s+de\s+Emissão[:;]?\s*(\d{2}[\s./-]\d{2}[\s./-]\d{2,4})", r"Data\s+Emissão[:;]?\s*(\d{2}[\s./-]\d{2}[\s./-]\d{2,4})", r"Data\s+do\s+Documento[:;]?\s*(\d{2}[\s./-]\d{2}[\s./-]\d{2,4})"])),
        'data_vencimento': lambda text: _to_iso_date(_find_first_match(text, [r"Data\s+do\s+Vencimento[:;]?\s*(\d{2}[\s./-]\d{2}[\s./-]\d{2,4})", r"Vencimento[:;]?\s*(\d{2}[\s./-]\d{2}[\s./-]\d{2,4})"])),
        'valor_total': lambda text: _to_float(_find_first_match(text, [
            r"TOTAL\s+DA\s+NOTA\s*R?\$\s*([\d.,]+)",
            r"Valor\s+Total\s+da\s+Fatura\s*R?\$\s*([\d.,]+)",
            r"TOTAL\s+A\s+PAGAR[:\s]*R\$?\s*([\d.,]+)",
            r"Valor\s+a\s+Pagar\s*R?\$\s*([\d.,]+)"
        ])),
        'descricao_servico': lambda text: _find_first_match(text, [r"Descrição\s+dos\s+Serviços[\s\S]*?\n(.*?)\s+\d{2}/\d{2}", r"Descrição[\s\S]*?\n(.*?)\s+\d+"]),
        'valor_servico': lambda text: _to_float(_find_first_match(text, [r"Valor\s+do\s+serviço\s*R?\$\s*([\d.,]+)", r"Valor\s+dos\s+Serviços\s*R?\$\s*([\d.,]+)"])),
    },
    'semparar': {
        'cnpj_beneficiario': lambda text: _find_first_match(text, [r"CNPJ:\s*([\d./-]+)"]),
        'nome_pagador': lambda text: _find_first_match(text, [r"Cliente:\s*(.*?)\s*Código"]),
        'cnpj_pagador': lambda text: _find_first_match(text, [r"CNPJ/CPF:\s*([\d./-]+)"]),
        'endereco_pagador': lambda text: _find_first_match(text, [r"Endereço:\s*(.*?)(?=CEP)"]),
        'numero_fatura': lambda text: _find_first_match(text, [r"Nº\s+da\s+Fatura:\s*(\d+)"]),
        'data_emissao': lambda text: _to_iso_date(_find_first_match(text, [r"Data\s+de\s+emissão:\s*(\d{2}/\d{2}/\d{4})"])),
        'data_vencimento': lambda text: _to_iso_date(_find_first_match(text, [r"Vencimento:\s*(\d{2}/\d{2}/\d{4})"])),
        'valor_total': lambda text: _to_float(_find_first_match(text, [r"Valor\s+a\s+pagar:\s*R?\$\s*([\d.,]+)"])),
        'descricao_servico': lambda text: _find_first_match(text, [r"Serviços\s+Habilitados[\s\S]*?\n(.*?)\s+\d{2}/\d{2}"]),
        'periodo_servico': lambda text: _find_first_match(text, [r"Período\s+de\s+utilização:\s*(.*?)\s*"]),
        'valor_servico': lambda text: _to_float(_find_first_match(text, [r"Plano\s+Contratado[\s\S]*?R?\$\s*([\d.,]+)"])),
        'placa_veiculo': lambda text: _find_first_match(text, [r"Placa:\s*([A-Z0-9]+)"]),
    }
}

def extract_details(text: str, category: str) -> dict:
    """
    Extrai detalhes de um texto de fatura com base na sua categoria.

    Args:
        text: O texto completo da fatura.
        category: A categoria da fatura (ex: 'energia', 'agua').

    Returns:
        Um dicionário com os detalhes extraídos.
    """
    if category not in EXTRACTION_RULES:
        return {}

    # Pré-processamento: normaliza variações comuns de labels que o OCR pode produzir
    # Exemplos: 'CONSUM.:', 'CONSUM.' -> 'CONSUM'
    norm_text = text or ''
    # Normaliza finais de label removendo pontos e dois-pontos extras (case-insensitive)
    norm_text = re.sub(r'(?i)(consum(?:idor|o)?)[\.:]+', r"\1", norm_text)
    # Junta tokens curtos em maiúsculas que foram separados por quebra de linha pelo OCR
    # Ex: 'CON\nSUM' -> 'CONSUM' — usa grupos de captura (lookbehind variável não é suportado)
    norm_text = re.sub(r'(?m)\b([A-Z]{1,4})\s*\n\s*([A-Z]{1,4})\b', r'\1\2', norm_text)
    # Também pode normalizar variações como 'Consum.' com maiúsculas/minúsculas
    # Preserva quebras de linha para que regexes que usam \n continuem funcionando.
    # Remove espaços/tabs duplicados (mas mantém novas linhas)
    norm_text = re.sub(r"[ \t]+", ' ', norm_text)
    # Normalize CRLF para LF
    norm_text = norm_text.replace('\r\n', '\n').replace('\r', '\n')

    rules = EXTRACTION_RULES[category]
    details = {}

    for field, extractor_func in rules.items():
        details[field] = extractor_func(norm_text)

    return details
