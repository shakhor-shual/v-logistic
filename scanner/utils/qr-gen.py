# Пример Python-скрипта для генерации QR
import qrcode
codes = ['REG-7a3f-b9c2-d4e1', 'REG-8b4g-c1d2-e3f4']
for i, code in enumerate(codes):
    qr = qrcode.make(code)
    qr.save(f'qr_{i+1}.png')
    
