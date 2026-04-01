// i18n.js - Internationalization module for Ventura Scan
class I18n {
    constructor() {
        this.currentLang = 'en';
        this.translations = {};
        this.observers = [];
        this.supportedLangs = [ 'pl', 'en', 'ru', 'uk', 'ka'];
        this.langNames = {
            pl: 'Polski',          
            en: 'English',
            ru: 'Русский',
            uk: 'Українська',
            ka: 'ქართული'  // Грузинский           
        };
    }
    
    detectBrowserLanguage() {
        const browserLang = navigator.language || navigator.userLanguage;
        const langCode = browserLang.split('-')[0].toLowerCase();
        return this.supportedLangs.includes(langCode) ? langCode : 'en';
    }
    
    async loadLanguage(lang) {
        try {
            const timestamp = Date.now();
            const response = await fetch(`locales/${lang}.json?v=${timestamp}`);
            if (!response.ok) throw new Error(`Failed to load ${lang}`);
            this.translations[lang] = await response.json();
            this.currentLang = lang;
            localStorage.setItem('preferred_lang', lang);
            this.notifyObservers();
            return true;
        } catch (error) {
            console.error('Error loading language:', error);
            return false;
        }
    }
    
    async init() {
        const savedLang = localStorage.getItem('preferred_lang');
        let initialLang = (savedLang && this.supportedLangs.includes(savedLang)) ? savedLang : this.detectBrowserLanguage();
        await this.loadLanguage(initialLang);
    }
    
    t(key, params = {}) {
        const translation = this.translations[this.currentLang];
        if (!translation) return key;
        let text = this.getNestedValue(translation, key);
        if (!text) return key;
        Object.keys(params).forEach(param => {
            text = text.replace(new RegExp(`{{${param}}}`, 'g'), params[param]);
        });
        return text;
    }
    
    getNestedValue(obj, path) {
        const keys = path.split('.');
        let current = obj;
        for (const key of keys) {
            if (current && typeof current === 'object' && key in current) {
                current = current[key];
            } else {
                return null;
            }
        }
        return current;
    }
    
    async setLanguage(lang) {
        if (!this.supportedLangs.includes(lang) || lang === this.currentLang) return false;
        return await this.loadLanguage(lang);
    }
    
    getCurrentLanguage() { return this.currentLang; }
    getSupportedLanguages() { return this.supportedLangs; }
    getLanguageName(lang) { return this.langNames[lang] || lang; }
    
    addObserver(callback) { this.observers.push(callback); }
    notifyObservers() { this.observers.forEach(callback => callback(this.currentLang)); }
    
    updateDOM() {
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            const translation = this.t(key);
            if (element.tagName === 'BUTTON' || element.tagName === 'DIV' || element.tagName === 'SPAN') {
                if (element.getAttribute('data-i18n-html') === 'true') {
                    element.innerHTML = translation;
                } else {
                    element.textContent = translation;
                }
            } else {
                element.innerHTML = translation;
            }
        });
        
        const titleKey = document.documentElement.getAttribute('data-i18n-title');
        if (titleKey) document.title = this.t(titleKey);
    }
}

const i18n = new I18n();
