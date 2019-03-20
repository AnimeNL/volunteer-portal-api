# Aanmelding afronden

Te gek dat je mee komt helpen met het stewardteam, <strong>{{name}}</strong>! Om je aanmelding af te
ronden hebben we nog een paar vragen voor je. Uiteraard kan je ons altijd via WhatsApp of e-mail een
berichtje sturen als je zelf ook nog vragen hebt.

<form action="/hallo/aanmelding.php" method="post">
  <ol>
    <li>
      <label for="naam">Naam:</label>
      <input type="text" required name="naam" id="naam" value="{{name}}" />
    </li>
    <li>
      <label for="email">E-mail adres:</label>
      <input type="text" required name="email" id="email" value="{{email}}" />
    </li>
  </ol>

{{training}}. De trainingen vinden plaats <a href="/hallo/training.html" target="_blank">in Amersfoort</a>,
en we zullen je deelname in april kunnen bevestigen. Welke datum(s) hebben je voorkeur?

  <ol>
    <li>
      <label for="training_no">⇾ <i>Ik volg de training dit jaar niet</i></label>
      <input type="checkbox" name="training_no" id="training_no" />
    </li>
    <li>
      <label for="training_05_25">⇾ Zaterdag 25 mei</label>
      <input type="checkbox" name="training_05_25" id="training_05_25" />
    </li>
    <li>
      <label for="training_05_26">⇾ Zondag 26 mei</label>
      <input type="checkbox" name="training_05_26" id="training_05_26" />
    </li>
    <li>
      <label for="training_06_01">⇾ Zaterdag 1 juni</label>
      <input type="checkbox" name="training_06_01" id="training_06_01" />
    </li>
    <li>
      <label for="training_06_02">⇾ Zondag 2 juni</label>
      <input type="checkbox" name="training_06_02" id="training_06_02" />
    </li>
  </ol>

Je hebt de mogelijkheid om via ons <a href="/hallo/hotel.html" target="_blank">een slaapplaats</a>
in het <a href="https://www.inntelhotelsrotterdamcentre.nl/">Inntel Hotel</a> te reserveren vanaf
€60 p.p.p.n., maar kunt zelf ook voor één van de alternatieve en/of goedkopere opties kiezen. Wil je
dat wij een kamer in het Inntel voor je regelen?

  <ol>
    <li>
      <label for="hotel">⇾ Yup, neem contact met me op!</label>
      <input type="checkbox" name="hotel" id="hotel" />
    </li>
  </ol>

Tenslotte hebben we je toestemming nodig voor onze <a href="/hallo/dataverwerking.html" target="_blank">dataverwerking</a>…

  <ol>
    <li>
      <label for="gdpr">⇾ Ga je hiermee akkoord?</label>
      <input type="checkbox" required name="gdpr" id="gdpr" />
    </li>
  </ol>

  <input type="submit" value="Verzenden" />
</form>

[« Voorpagina](index.html)
