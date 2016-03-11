# Registratieformulier

Te gek! Vul het volgende formulier in en we zullen snel contact met je opnemen!

<p style="display: none" id="error" class="error">Eén van de velden was niet goed ingevuld, sorry! Probeer het nog eens.</p>
<script>
if (document.location.hash == '#error')
  document.getElementById('error').style.display = 'block';
</script>

<form action="registratie.php" method="post">
  <ol>
    <li>
      <label for="naam">Naam:</label>
      <input type="text" required name="naam" id="naam" />
    </li>
    <li>
      <label for="geboortedatum">Geboortedatum:</label>
      <input type="date" required name="geboortedatum" id="geboortedatum" />
    </li>
    <li>
      <label for="email">E-mail adres:</label>
      <input type="email" name="email" id="email" />
    </li>
    <li>
      <label for="haarkleur">Haarkleur:</label>
      <input type="text" required name="haarkleur" id="haarkleur" />
    </li>
    <li>
      <label for="telefoonnummer">Telefoonnummer:</label>
      <input type="text" required name="telefoonnummer" id="telefoonnummer" />
    </li>
    <li>
      <label for="forumnickname"><a href="https://forum.animecon.nl" target="_blank">Forum</a> nickname:</label>
      <input type="text" name="forumnickname" id="forumnickname" />
    </li>
  </ol>

Daarnaast hebben we ook nog wat vragen over je eerdere ervaringen betreft veiligheid, stewarden en
vrijwilligerswerk.

  <ol>
    <li>
      <label for="bhv">Ben je BHV gecertificeerd?</label>
      <input type="checkbox" name="bhv" id="bhv" />
    </li>
    <li>
      <label for="ehbo">Ben je EHBO gecertificeerd?</label>
      <input type="checkbox" name="ehbo" id="ehbo" />
    </li>
    <li>
      <label for="stewardtraining">Stewardtraining in 2015 gevolgd? <span style="color: red">*</span></label>
      <input type="checkbox" name="stewardtraining" id="stewardtraining" />
    </li>
    <li>
      <label for="ervaring">Verdere relevante ervaring?</label>
      <input type="text" name="ervaring" id="ervaring" />
    </li>
  </ol>

  <input type="submit" value="Verzenden" />
</form>

<span style="font-size: .8em"><span style="color: red">*</span> Als je in 2015 een stewardtraining
via Animecon óf Abunai hebt gevolgt, dan is de training in 2016 optioneel.</span>

[« Vorige pagina](index.html)
